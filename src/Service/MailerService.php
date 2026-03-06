<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\RegistrationStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail,
        private readonly string $senderName
    ) {
    }

    private function send(string $to, string $subject, string $message, ?string $replyTo = null): bool
    {
        if ($to === '') {
            $this->logger->error('Email vide');

            return false;
        }

        try {
            $email = (new Email())
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to($to)
                ->subject($subject)
                ->text($message);

            if ($replyTo !== null && $replyTo !== '') {
                $email->replyTo($replyTo);
            }

            $this->mailer->send($email);
            $this->logger->info('Email envoye a: '.$to);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur email vers '.$to.': '.$e->getMessage());

            return false;
        }
    }

    private function appUrl(): string
    {
        $url = getenv('APP_URL');

        return is_string($url) && $url !== '' ? $url : 'http://127.0.0.1:8000';
    }

    private function founder(Club $club): ?User
    {
        $founder = $club->getFounder();

        return $founder instanceof User ? $founder : null;
    }

    public function sendClubCreationConfirmation(User $user, Club $club): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Felicitations ! Votre club \"{$club->getTitle()}\" a ete cree avec succes.\n\n"
            ."Vous pouvez le gerer ici : ".$this->appUrl()."/club/member-view/{$club->getId()}\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            'Felicitations ! Votre club "'.$club->getTitle().'" a ete cree',
            $message
        );
    }

    public function sendWelcomeToNewMember(User $user, Club $club): bool
    {
        $founder = $this->founder($club);
        $founderName = $founder instanceof User
            ? trim($founder->getFirstName().' '.$founder->getLastName())
            : 'Fondateur inconnu';
        $replyTo = $founder?->getEmail();

        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Bienvenue dans le club \"{$club->getTitle()}\" !\n\n"
            ."Fondateur : {$founderName}\n"
            .'Membres : '.count($club->getMembers())."\n\n"
            ."Accedez au club : ".$this->appUrl()."/club/member-view/{$club->getId()}\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            'Bienvenue dans le club "'.$club->getTitle().'" !',
            $message,
            $replyTo
        );
    }

    public function sendNewMemberNotificationToFounder(User $newMember, Club $club): bool
    {
        $founder = $this->founder($club);
        if (!$founder instanceof User) {
            return false;
        }

        $message = "Bonjour {$founder->getFirstName()},\n\n"
            ."Un nouveau membre a rejoint votre club \"{$club->getTitle()}\" :\n"
            ."- Nom : {$newMember->getFirstName()} {$newMember->getLastName()}\n"
            ."- Email : {$newMember->getEmail()}\n\n"
            .'Total membres : '.count($club->getMembers())."\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $founder->getEmail(),
            'Nouveau membre dans "'.$club->getTitle().'"',
            $message
        );
    }

    public function sendMemberLeftNotificationToFounder(User $member, Club $club): bool
    {
        $founder = $this->founder($club);
        if (!$founder instanceof User) {
            return false;
        }

        $message = "Bonjour {$founder->getFirstName()},\n\n"
            ."Un membre a quitte votre club \"{$club->getTitle()}\" :\n"
            ."- Nom : {$member->getFirstName()} {$member->getLastName()}\n"
            ."- Email : {$member->getEmail()}\n\n"
            .'Nouveau total membres : '.count($club->getMembers())."\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $founder->getEmail(),
            'Un membre a quitte "'.$club->getTitle().'"',
            $message
        );
    }

    /**
     * @return array<string, bool>
     */
    public function sendClubUpdateNotification(Club $club): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            ."Le club \"{$club->getTitle()}\" a ete modifie.\n\n"
            ."Voir les changements : ".$this->appUrl()."/club/member-view/{$club->getId()}\n\n"
            ."L'equipe LibraryHub";
        $founder = $this->founder($club);

        foreach ($club->getMembers() as $member) {
            if ($member === $founder) {
                continue;
            }

            $email = $member->getEmail();
            $results[$email] = $this->send(
                $email,
                'Mise a jour : Club "'.$club->getTitle().'"',
                "Bonjour {$member->getFirstName()},\n\n".$message
            );
        }

        return $results;
    }

    /**
     * @return array<string, bool>
     */
    public function sendNewEventNotification(Club $club, Event $event): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            ."Un nouvel evenement a ete cree dans le club \"{$club->getTitle()}\" :\n"
            ."- Titre : {$event->getTitle()}\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            .'Details : '.$this->appUrl()."/event/{$event->getId()}\n\n"
            ."L'equipe LibraryHub";

        foreach ($club->getMembers() as $member) {
            $email = $member->getEmail();
            $results[$email] = $this->send(
                $email,
                'Nouvel evenement : '.$event->getTitle().' dans '.$club->getTitle(),
                "Bonjour {$member->getFirstName()},\n\n".$message
            );
        }

        return $results;
    }

    /**
     * @return array{old: bool, new: bool}
     */
    public function sendOwnershipTransferNotification(User $oldFounder, User $newFounder, Club $club): array
    {
        $old = $this->send(
            $oldFounder->getEmail(),
            'Transfert de propriete : Club "'.$club->getTitle().'"',
            "Bonjour {$oldFounder->getFirstName()},\n\n"
            ."Vous avez transfere la propriete du club \"{$club->getTitle()}\" a {$newFounder->getFirstName()} {$newFounder->getLastName()}.\n\n"
            ."L'equipe LibraryHub"
        );

        $new = $this->send(
            $newFounder->getEmail(),
            'Vous etes maintenant fondateur du club "'.$club->getTitle().'"',
            "Bonjour {$newFounder->getFirstName()},\n\n"
            ."Felicitations ! Vous etes maintenant le fondateur du club \"{$club->getTitle()}\".\n\n"
            .'Gerez votre club : '.$this->appUrl()."/club/member-view/{$club->getId()}\n\n"
            ."L'equipe LibraryHub"
        );

        return ['old' => $old, 'new' => $new];
    }

    /**
     * @return array<string, bool>
     */
    public function sendClubDeletionNotification(Club $club): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            ."Le club \"{$club->getTitle()}\" a ete supprime.\n\n"
            ."Vous pouvez decouvrir d'autres clubs : ".$this->appUrl()."/club/decouvrir\n\n"
            ."L'equipe LibraryHub";

        foreach ($club->getMembers() as $member) {
            $email = $member->getEmail();
            $results[$email] = $this->send(
                $email,
                'Le club "'.$club->getTitle().'" a ete supprime',
                "Bonjour {$member->getFirstName()},\n\n".$message
            );
        }

        return $results;
    }

    public function sendTestEmail(string $to): bool
    {
        return $this->send(
            $to,
            'Test de configuration email LibraryHub',
            "Bonjour,\n\nCeci est un email de test de LibraryHub.\n\nSi vous recevez ce message, la configuration email fonctionne !\n\nL'equipe LibraryHub"
        );
    }

    public function sendEventRegistrationConfirmation(User $user, Event $event): bool
    {
        $organizers = [];
        foreach ($event->getOrganizingClubs() as $club) {
            $organizers[] = $club->getTitle();
        }

        $organizerText = $organizers !== [] ? implode(', ', $organizers) : 'Non specifie';
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Votre inscription a l'evenement \"{$event->getTitle()}\" a ete confirmee !\n\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n"
            ."- Organise par : ".$organizerText."\n\n"
            .'Details complets : '.$this->appUrl()."/event/{$event->getId()}\n\n"
            ."Nous avons hate de vous y voir !\n\n"
            ."L'equipe LibraryHub";

        $replyTo = $event->getCreatedBy()?->getEmail();

        return $this->send(
            $user->getEmail(),
            'Inscription confirmee : '.$event->getTitle(),
            $message,
            $replyTo
        );
    }

    public function sendEventWaitlistNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Vous avez ete place(e) en liste d'attente pour l'evenement \"{$event->getTitle()}\".\n\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            ."Vous serez automatiquement inscrit(e) et notifie(e) si une place se libere.\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            "Liste d'attente : ".$event->getTitle(),
            $message
        );
    }

    public function sendEventCancellationNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Vous vous etes desinscrit(e) de l'evenement \"{$event->getTitle()}\".\n\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            ."Si vous changez d'avis, vous pouvez vous reinscrire tant qu'il reste des places.\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            'Desinscription : '.$event->getTitle(),
            $message
        );
    }

    public function sendEventSpotFreedNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."Bonne nouvelle ! Une place s'est liberee pour l'evenement \"{$event->getTitle()}\".\n\n"
            ."Votre inscription a ete automatiquement confirmee.\n\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            .'Details : '.$this->appUrl()."/event/{$event->getId()}\n\n"
            ."Nous avons hate de vous y voir !\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            'Place liberee : '.$event->getTitle(),
            $message
        );
    }

    /**
     * @param list<string> $changes
     * @return array<string, bool>
     */
    public function sendEventUpdateNotification(Event $event, array $changes): array
    {
        $results = [];
        $changesText = '';
        foreach ($changes as $change) {
            $changesText .= '- '.$change."\n";
        }

        $message = "Bonjour,\n\n"
            ."L'evenement \"{$event->getTitle()}\" a ete modifie :\n\n"
            .$changesText."\n"
            .'Nouveaux details complets : '.$this->appUrl()."/event/{$event->getId()}\n\n"
            ."Merci de votre comprehension.\n\n"
            ."L'equipe LibraryHub";

        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() !== RegistrationStatus::CONFIRMED) {
                continue;
            }

            $user = $registration->getUser();
            if (!$user instanceof User) {
                continue;
            }

            $email = $user->getEmail();
            $results[$email] = $this->send(
                $email,
                'Mise a jour : '.$event->getTitle(),
                "Bonjour {$user->getFirstName()},\n\n".$message
            );
        }

        return $results;
    }

    /**
     * @return array<string, bool>
     */
    public function sendEventDeletionNotification(Event $event): array
    {
        $results = [];
        $message = "Bonjour,\n\n"
            ."Nous sommes desoles de vous informer que l'evenement \"{$event->getTitle()}\" a ete annule.\n\n"
            ."- Date initiale : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            ."Nous vous invitons a decouvrir d'autres evenements : ".$this->appUrl()."/event/discover\n\n"
            ."Merci de votre comprehension.\n\n"
            ."L'equipe LibraryHub";

        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() !== RegistrationStatus::CONFIRMED) {
                continue;
            }

            $user = $registration->getUser();
            if (!$user instanceof User) {
                continue;
            }

            $email = $user->getEmail();
            $results[$email] = $this->send(
                $email,
                'Evenement annule : '.$event->getTitle(),
                "Bonjour {$user->getFirstName()},\n\n".$message
            );
        }

        return $results;
    }

    public function sendEventReminderNotification(User $user, Event $event): bool
    {
        $message = "Bonjour {$user->getFirstName()},\n\n"
            ."RAPPEL : L'evenement \"{$event->getTitle()}\" a lieu demain !\n\n"
            ."- Date : ".$event->getStartDateTime()->format('d/m/Y H:i')."\n"
            ."- Lieu : ".($event->getLocation() ?: 'A determiner')."\n\n"
            .'Details : '.$this->appUrl()."/event/{$event->getId()}\n\n"
            ."A tres bientot !\n\n"
            ."L'equipe LibraryHub";

        return $this->send(
            $user->getEmail(),
            'Rappel : '.$event->getTitle().' demain !',
            $message
        );
    }
}
