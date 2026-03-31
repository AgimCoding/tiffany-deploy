<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Application\Service\MailerService;
use App\Application\Service\PushNotificationService;
use App\Domain\Port\AppointmentRepositoryInterface;
use App\Domain\Port\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reminders:noshow', description: 'Relance les clients qui n\'ont pas repris RDV depuis X jours')]
final class SendNoShowRemindersCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly AppointmentRepositoryInterface $appointmentRepo,
        private readonly MailerService $mailerService,
        private readonly PushNotificationService $pushService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours sans RDV', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $output->writeln("Recherche des clients sans RDV depuis {$days} jours ({$cutoffDate->format('Y-m-d')})...");

        $users = $this->userRepo->findAll();
        $sent = 0;

        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                continue;
            }

            // Get the user's most recent completed appointment
            $appointments = $this->appointmentRepo->findByUser($user->getId());
            if (empty($appointments)) {
                continue;
            }

            // Find last completed appointment
            $lastCompleted = null;
            foreach ($appointments as $apt) {
                if ($apt->getStatus()->value === 'completed') {
                    if (!$lastCompleted || $apt->getDate() > $lastCompleted->getDate()) {
                        $lastCompleted = $apt;
                    }
                }
            }

            if (!$lastCompleted) {
                continue;
            }

            // Check if last visit was before the cutoff
            if ($lastCompleted->getDate() > $cutoffDate) {
                continue;
            }

            // Check they don't have an upcoming appointment
            $hasFuture = false;
            $today = new \DateTimeImmutable('today');
            foreach ($appointments as $apt) {
                if ($apt->getDate() >= $today && in_array($apt->getStatus()->value, ['pending', 'confirmed'])) {
                    $hasFuture = true;
                    break;
                }
            }

            if ($hasFuture) {
                continue;
            }

            // Send reminder email
            $daysSinceLast = (int) $lastCompleted->getDate()->diff(new \DateTimeImmutable())->days;
            $this->mailerService->send(
                $user->getEmail(),
                'Vous nous manquez !',
                sprintf(
                    '<div style="text-align:center;font-family:Georgia,serif;max-width:500px;margin:0 auto;padding:40px 20px;">
                        <h1 style="color:#b8860b;font-size:1.5rem;">TIFFANY</h1>
                        <p>Bonjour %s,</p>
                        <p>Cela fait <strong>%d jours</strong> que nous ne vous avons pas vue !</p>
                        <p>Prenez rendez-vous et retrouvez une coiffure qui vous ressemble.</p>
                        <p style="margin-top:25px;">
                            <a href="https://tiffany.garagepro.be/#reservation" style="background:#b8860b;color:#fff;padding:12px 30px;text-decoration:none;border-radius:5px;font-size:0.9rem;">PRENDRE RENDEZ-VOUS</a>
                        </p>
                        <p style="color:#888;font-size:0.8rem;margin-top:25px;">Les Creations de Tiffany - 0497 92 60 03</p>
                    </div>',
                    $user->getFullName(),
                    $daysSinceLast,
                ),
            );

            // Push notification
            $this->pushService->sendToUser(
                $user->getId(),
                'Vous nous manquez !',
                "Cela fait {$daysSinceLast} jours. Prenez rendez-vous !",
                '/#reservation',
                'noshow-reminder',
            );

            $sent++;
            $output->writeln("  -> Relance envoyee a {$user->getFullName()} ({$daysSinceLast} jours)");
        }

        $output->writeln("Termine : {$sent} relance(s) envoyee(s).");
        return Command::SUCCESS;
    }
}
