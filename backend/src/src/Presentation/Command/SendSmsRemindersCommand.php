<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Application\Service\SmsService;
use App\Domain\Port\AppointmentRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sms:reminders',
    description: 'Send SMS reminders for appointments happening tomorrow',
)]
final class SendSmsRemindersCommand extends Command
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly SmsService $smsService,
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
            if ($appointment->getStatus() === 'cancelled') {
                continue;
            }

            $phone = $appointment->getUser()->getPhone();
            if (!$phone) {
                $skipped++;
                continue;
            }

            $dateFR = $tomorrow->format('d/m/Y');
            $ok = $this->smsService->sendAppointmentReminder(
                $phone,
                $appointment->getUser()->getFullName(),
                $appointment->getService()->getName(),
                $dateFR,
                $appointment->getTimeSlot(),
            );

            if ($ok) {
                $sent++;
                $io->writeln(sprintf('SMS sent to %s for %s at %s', $phone, $appointment->getService()->getName(), $appointment->getTimeSlot()));
            } else {
                $skipped++;
                $io->warning(sprintf('Failed to send SMS to %s', $phone));
            }
        }

        $io->success(sprintf('%d SMS sent, %d skipped.', $sent, $skipped));

        return Command::SUCCESS;
    }
}
