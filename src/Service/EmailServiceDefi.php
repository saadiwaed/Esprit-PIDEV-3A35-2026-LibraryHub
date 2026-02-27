<?php

namespace App\Service;

use App\Entity\DefiPersonel;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailServiceDefi // ✅ Nom de classe adapté
{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Envoie un email de félicitations quand un défi est terminé
     */
    public function envoyerFelicitationsDefi(User $user, DefiPersonel $defi): void
    {
        // Créer l'email avec template Twig
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAILER_SENDER_EMAIL'] ?? 'no-reply@libraryhub.com', $_ENV['MAILER_SENDER_NAME'] ?? 'LibraryHub'))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('🎉 FÉLICITATIONS ! Défi terminé sur LibraryHub')
            ->htmlTemplate('emails/defi_termine.html.twig')
            ->context([
                'user' => $user,
                'defi' => $defi,
                'progression' => $defi->getProgression(),
                'objectif' => $defi->getObjectif(),
                'unite' => $defi->getUnite(),
                'date_fin' => $defi->getDateFin(),
                'annee' => date('Y'),
                'app_url' => $_ENV['APP_URL'] ?? 'http://127.0.0.1:8000',
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'application
            error_log('Erreur envoi email: ' . $e->getMessage());
        }
    }
}