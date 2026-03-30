<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Application\Service\MailerService;
use App\Domain\Port\AppointmentRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:email:reminders',
    description: 'Send email reminders for appointments happening tomorrow',
)]
final class SendEmailRemindersCommand extends Command
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly MailerService $mailerService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $appointments = $this->appointmentRepository->findByDate($tomorrow);

        $sent = 0;
        $skipped = 0;

        foreach ($appointments as $appointment) {
            if (in_array($appointment->getStatus(), ['cancelled', 'completed'], true)) {
                continue;
            }

            $email = $appointment->getUser()->getEmail();
            if (!$email) {
                $skipped++;
                continue;
            }

            $dateFR = $tomorrow->format('d/m/Y');
            $ok = $this->mailerService->sendAppointmentReminder(
                $email,
                $appointment->getUser()->getFullName(),
                $appointment->getService()->getName(),
                $dateFR,
                $appointment->getTimeSlot(),
            );

            if ($ok) {
                $sent++;
                $io->writeln(sprintf('Email sent to %s for %s at %s', $email, $appointment->getService()->getName(), $appointment->getTimeSlot()));
            } else {
                $skipped++;
                $io->warning(sprintf('Failed to send email to %s', $email));
            }
        }

        $io->success(sprintf('%d emails sent, %d skipped.', $sent, $skipped));

        return Command::SUCCESS;
    }
}
