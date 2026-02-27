<?php
// src/Service/MailerService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\Club;
use App\Entity\Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class MailerService
{
    private $mailer;
    private $logger;
    private $senderEmail;
    private $senderName;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $senderEmail,
        string $senderName
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
    }

    /**
     * Version SANS templates - emails en texte simple
     */
    private function send(string $to, string $subject, string $message, ?string $replyTo = null): bool
{
    if (empty($to)) {
        $this->logger->error('Email vide');
        return false;
    }

    try {
        $email = (new Email())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($to)
            ->subject($subject)
            ->text($message);

        if ($replyTo) {
            $email->replyTo($replyTo);
        }

        $this->mailer->send($email);

        $this->logger->info('Email envoyé à: ' . $to);
        return true;

    } catch (\Throwable $e) {
        $this->logger->error('Erreur email vers ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

    /**
     * 1. Email de confirmation - Création de club
     */
    public function sendClubCreationConfirmation(User $user, Club $club): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "Félicitations ! Votre club \"{$club->getTitle()}\" a été créé avec succès.\n\n"
            . "Vous pouvez le gérer ici : " . getenv('APP_URL') . "/club/member-view/{$club->getId()}\n\n"
            . "L'équipe LibraryHub";
        
        return $this->send(
            $user->getEmail(),
            '🎉 Félicitations ! Votre club "' . $club->getTitle() . '" a été créé',
            $message
        );
    }

    /**
     * 2. Email de bienvenue - Nouveau membre
     */
    public function sendWelcomeToNewMember(User $user, Club $club): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "Bienvenue dans le club \"{$club->getTitle()}\" !\n\n"
            . "Fondateur : {$club->getFounder()->getFirstName()} {$club->getFounder()->getLastName()}\n"
            . "Membres : " . count($club->getMembers()) . "\n\n"
            . "Accédez au club : " . getenv('APP_URL') . "/club/member-view/{$club->getId()}\n\n"
            . "L'équipe LibraryHub";
        
        return $this->send(
            $user->getEmail(),
            '👋 Bienvenue dans le club "' . $club->getTitle() . '" !',
            $message,
            $club->getFounder()->getEmail()
        );
    }

    /**
     * 3. Email de notification - Nouveau membre au fondateur
     */
    public function sendNewMemberNotificationToFounder(User $newMember, Club $club): bool
    {
        $message = "Bonjour {$club->getFounder()->getFirstName()},\n\n"
            . "Un nouveau membre a rejoint votre club \"{$club->getTitle()}\" :\n"
            . "- Nom : {$newMember->getFirstName()} {$newMember->getLastName()}\n"
            . "- Email : {$newMember->getEmail()}\n\n"
            . "Total membres : " . count($club->getMembers()) . "\n\n"
            . "L'équipe LibraryHub";
        
        return $this->send(
            $club->getFounder()->getEmail(),
            '👥 Nouveau membre dans "' . $club->getTitle() . '"',
            $message
        );
    }

    /**
     * 4. Email de notification - Membre a quitté
     */
    public function sendMemberLeftNotificationToFounder(User $member, Club $club): bool
    {
        $message = "Bonjour {$club->getFounder()->getFirstName()},\n\n"
            . "Un membre a quitté votre club \"{$club->getTitle()}\" :\n"
            . "- Nom : {$member->getFirstName()} {$member->getLastName()}\n"
            . "- Email : {$member->getEmail()}\n\n"
            . "Nouveau total membres : " . count($club->getMembers()) . "\n\n"
            . "L'équipe LibraryHub";
        
        return $this->send(
            $club->getFounder()->getEmail(),
            '👋 Un membre a quitté "' . $club->getTitle() . '"',
            $message
        );
    }

    /**
     * 5. Email de notification - Modification du club
     */
    public function sendClubUpdateNotification(Club $club): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            . "Le club \"{$club->getTitle()}\" a été modifié.\n\n"
            . "Voir les changements : " . getenv('APP_URL') . "/club/member-view/{$club->getId()}\n\n"
            . "L'équipe LibraryHub";
        
        foreach ($club->getMembers() as $member) {
            if ($member !== $club->getFounder()) {
                $results[$member->getEmail()] = $this->send(
                    $member->getEmail(),
                    '🔔 Mise à jour : Club "' . $club->getTitle() . '"',
                    "Bonjour {$member->getFirstName()},\n\n" . $message
                );
            }
        }
        return $results;
    }

    /**
     * 6. Email de notification - Nouvel événement
     */
    public function sendNewEventNotification(Club $club, Event $event): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            . "Un nouvel événement a été créé dans le club \"{$club->getTitle()}\" :\n"
            . "- Titre : {$event->getTitle()}\n"
            . "- Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "- Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Détails : " . getenv('APP_URL') . "/event/{$event->getId()}\n\n"
            . "L'équipe LibraryHub";
        
        foreach ($club->getMembers() as $member) {
            $results[$member->getEmail()] = $this->send(
                $member->getEmail(),
                '📅 Nouvel événement : ' . $event->getTitle() . ' dans ' . $club->getTitle(),
                "Bonjour {$member->getFirstName()},\n\n" . $message
            );
        }
        return $results;
    }

    /**
     * 7. Email de notification - Transfert de propriété
     */
    public function sendOwnershipTransferNotification(User $oldFounder, User $newFounder, Club $club): array
    {
        $results = [];
        
        // À l'ancien fondateur
        $results['old'] = $this->send(
            $oldFounder->getEmail(),
            '👑 Transfert de propriété : Club "' . $club->getTitle() . '"',
            "Bonjour {$oldFounder->getFirstName()},\n\n"
            . "Vous avez transféré la propriété du club \"{$club->getTitle()}\" à {$newFounder->getFirstName()} {$newFounder->getLastName()}.\n\n"
            . "L'équipe LibraryHub"
        );
        
        // Au nouveau fondateur
        $results['new'] = $this->send(
            $newFounder->getEmail(),
            '👑 Vous êtes maintenant fondateur du club "' . $club->getTitle() . '"',
            "Bonjour {$newFounder->getFirstName()},\n\n"
            . "Félicitations ! Vous êtes maintenant le fondateur du club \"{$club->getTitle()}\".\n\n"
            . "Gérez votre club : " . getenv('APP_URL') . "/club/member-view/{$club->getId()}\n\n"
            . "L'équipe LibraryHub"
        );
        
        return $results;
    }

    /**
     * 8. Email de notification - Suppression du club
     */
    public function sendClubDeletionNotification(Club $club): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            . "Le club \"{$club->getTitle()}\" a été supprimé.\n\n"
            . "Vous pouvez découvrir d'autres clubs : " . getenv('APP_URL') . "/club/decouvrir\n\n"
            . "L'équipe LibraryHub";
        
        foreach ($club->getMembers() as $member) {
            $results[$member->getEmail()] = $this->send(
                $member->getEmail(),
                '❌ Le club "' . $club->getTitle() . '" a été supprimé',
                "Bonjour {$member->getFirstName()},\n\n" . $message
            );
        }
        return $results;
    }

    /**
     * 9. Email de test
     */
    public function sendTestEmail(string $to): bool
    {
        return $this->send(
            $to,
            '✅ Test de configuration email LibraryHub',
            "Bonjour,\n\nCeci est un email de test de LibraryHub.\n\nSi vous recevez ce message, la configuration email fonctionne !\n\nL'équipe LibraryHub"
        );
    }
        // ============================================
    // MÉTHODES POUR LES ÉVÉNEMENTS (À AJOUTER)
    // ============================================

    /**
     * 1. Notification de confirmation d'inscription à un événement
     */
    public function sendEventRegistrationConfirmation(User $user, Event $event): bool
    {
        // Récupérer les clubs organisateurs pour les infos
        $organizers = [];
        foreach ($event->getOrganizingClubs() as $club) {
            $organizers[] = $club->getTitle();
        }
        $organizerText = !empty($organizers) ? implode(', ', $organizers) : 'Non spécifié';

        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "Votre inscription à l'événement \"{$event->getTitle()}\" a été confirmée !\n\n"
            . "📅 Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n"
            . "👥 Organisé par : " . $organizerText . "\n\n"
            . "Détails complets : " . getenv('APP_URL') . "/event/{$event->getId()}\n\n"
            . "Nous avons hâte de vous y voir !\n\n"
            . "L'équipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            '✅ Inscription confirmée : ' . $event->getTitle(),
            $message,
            $event->getCreatedBy()?->getEmail()
        );
    }

    /**
     * 2. Notification de liste d'attente pour un événement
     */
    public function sendEventWaitlistNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "Vous avez été placé(e) en liste d'attente pour l'événement \"{$event->getTitle()}\".\n\n"
            . "📅 Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Vous serez automatiquement inscrit(e) et notifié(e) si une place se libère.\n\n"
            . "L'équipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            '⏳ Liste d\'attente : ' . $event->getTitle(),
            $message
        );
    }

    /**
     * 3. Notification de désinscription d'un événement
     */
    public function sendEventCancellationNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "Vous vous êtes désinscrit(e) de l'événement \"{$event->getTitle()}\".\n\n"
            . "📅 Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Si vous changez d'avis, vous pouvez vous réinscrire tant qu'il reste des places.\n\n"
            . "L'équipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            '👋 Désinscription : ' . $event->getTitle(),
            $message
        );
    }

    /**
     * 4. Notification de place libérée (passage de liste d'attente à confirmé)
     */
    public function sendEventSpotFreedNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "🎉 Bonne nouvelle ! Une place s'est libérée pour l'événement \"{$event->getTitle()}\".\n\n"
            . "Votre inscription a été automatiquement confirmée.\n\n"
            . "📅 Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Détails : " . getenv('APP_URL') . "/event/{$event->getId()}\n\n"
            . "Nous avons hâte de vous y voir !\n\n"
            . "L'équipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            '✅ Place libérée : ' . $event->getTitle(),
            $message
        );
    }

    /**
     * 5. Notification de modification d'événement (à tous les inscrits)
     */
    public function sendEventUpdateNotification(Event $event, array $changes): array
    {
        $results = [];
        
        // Construire le message avec les changements
        $changesText = "";
        foreach ($changes as $change) {
            $changesText .= "- " . $change . "\n";
        }

        $message = "Bonjour,\n\n"
            . "L'événement \"{$event->getTitle()}\" a été modifié :\n\n"
            . $changesText . "\n"
            . "Nouveaux détails complets : " . getenv('APP_URL') . "/event/{$event->getId()}\n\n"
            . "Merci de votre compréhension.\n\n"
            . "L'équipe LibraryHub";

        // Envoyer à tous les inscrits confirmés
        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() === \App\Enum\RegistrationStatus::CONFIRMED) {
                $user = $registration->getUser();
                $results[$user->getEmail()] = $this->send(
                    $user->getEmail(),
                    '📝 Mise à jour : ' . $event->getTitle(),
                    "Bonjour {$user->getFirstName()},\n\n" . $message
                );
            }
        }
        
        return $results;
    }

    /**
     * 6. Notification d'annulation d'événement (à tous les inscrits)
     */
    public function sendEventDeletionNotification(Event $event): array
    {
        $results = [];
        
        $message = "Bonjour,\n\n"
            . "Nous sommes désolés de vous informer que l'événement \"{$event->getTitle()}\" a été annulé.\n\n"
            . "📅 Date initiale : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Nous vous invitons à découvrir d'autres événements : " . getenv('APP_URL') . "/event/discover\n\n"
            . "Merci de votre compréhension.\n\n"
            . "L'équipe LibraryHub";

        // Envoyer à tous les inscrits confirmés
        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() === \App\Enum\RegistrationStatus::CONFIRMED) {
                $user = $registration->getUser();
                $results[$user->getEmail()] = $this->send(
                    $user->getEmail(),
                    '❌ Événement annulé : ' . $event->getTitle(),
                    "Bonjour {$user->getFirstName()},\n\n" . $message
                );
            }
        }
        
        return $results;
    }

    /**
     * 7. Rappel d'événement (J-1)
     */
    public function sendEventReminderNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            . "🔔 RAPPEL : L'événement \"{$event->getTitle()}\" a lieu demain !\n\n"
            . "📅 Date : " . $event->getStartDateTime()->format('d/m/Y H:i') . "\n"
            . "📍 Lieu : " . ($event->getLocation() ?: 'À déterminer') . "\n\n"
            . "Détails : " . getenv('APP_URL') . "/event/{$event->getId()}\n\n"
            . "À très bientôt !\n\n"
            . "L'équipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            '🔔 Rappel : ' . $event->getTitle() . ' demain !',
            $message
        );
    }
}