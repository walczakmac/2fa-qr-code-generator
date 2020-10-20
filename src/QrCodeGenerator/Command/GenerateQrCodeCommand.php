<?php

namespace QrCodeGenerator\Command;

use OTPHP\TOTP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class GenerateQrCodeCommand extends Command
{
    protected static string $defaultName = 'qrcode:generate';
    private string $qrUri = 'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M';

    private MailerInterface $mailer;
    private string $projectDir;

    public function __construct(MailerInterface $mailer, string $projectDir)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->projectDir = $projectDir;
    }

    protected function configure()
    {
        $this->addArgument('secret', InputOption::VALUE_REQUIRED);
        $this->addArgument('email', InputOption::VALUE_REQUIRED);
        $this->addArgument('recipient', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $secret = $input->getArgument('secret');
        $email = $input->getArgument('email');
        $recipient = $input->getArgument('recipient');

        $this->generateQrCode($secret, $email);
        $this->sendEmailMessage($recipient, $email);

        return 1;
    }

    private function generateQrCode(string $secret, string $email, string $issuer = 'ibanq') : void
    {
        $totp = TOTP::create($secret);
        $totp->setLabel($email);
        $totp->setIssuer($issuer);

        $url = $totp->getQrCodeUri($this->qrUri, '[DATA]');

        $qrCode = file_get_contents($url);
        file_put_contents($this->projectDir.'/var/cache/qrcode.png', $qrCode);
    }

    private function sendEmailMessage(string $recipient, string $email)
    {
        $message = (new Email())
            ->from('mwalczak@ifxpayments.com')
            ->to($recipient)
            ->attachFromPath($this->projectDir.'/var/cache/qrcode.png')
            ->subject('QR code for '.$email)
            ->text('QR code attached');

        $this->mailer->send($message);
    }
}
