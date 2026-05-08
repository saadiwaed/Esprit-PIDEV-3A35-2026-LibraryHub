<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StripeConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MembershipController extends AbstractController
{
    public function __construct(
        private StripeConfigService $stripeConfig,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/abonnement', name: 'app_membership_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('membership/index.html.twig');
    }

    #[Route('/abonnement/checkout/{plan}', name: 'app_membership_checkout', requirements: ['plan' => 'monthly|annual'], methods: ['GET'])]
    public function checkout(string $plan, Request $request): RedirectResponse|Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$this->stripeConfig->isConfigured()) {
            $this->addFlash('error', 'Le paiement Stripe n\'est pas configuré. Ajoutez STRIPE_SECRET_KEY dans .env.local.');
            return $this->redirectToRoute('app_membership_index');
        }

        $priceId = $this->stripeConfig->getPriceId($plan);
        if ($priceId === '') {
            $detail = $this->stripeConfig->getLastErrorMessage();
            $msg = $detail
                ? 'Impossible de récupérer le prix Stripe : ' . $detail
                : 'Impossible de récupérer le prix Stripe pour ce plan.';
            $this->addFlash('error', $msg);
            return $this->redirectToRoute('app_membership_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour vous abonner.');
            return $this->redirectToRoute('app_login');
        }

        Stripe::setApiKey($this->stripeConfig->getSecretKey());

        $baseUrl = $request->getSchemeAndHttpHost();
        $successUrl = $baseUrl . $this->generateUrl('app_membership_success', ['plan' => $plan]);
        $successUrl .= (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $baseUrl . $this->generateUrl('app_membership_cancel');

        try {
            $session = StripeSession::create([
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => $user->getUserIdentifier(),
                'metadata' => [
                    'plan' => $plan,
                    'user_id' => (string) $user->getId(),
                ],
                'subscription_data' => [
                    'metadata' => ['plan' => $plan],
                ],
            ]);
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Impossible de créer la session de paiement : ' . $e->getMessage());
            return $this->redirectToRoute('app_membership_index');
        }

        return new RedirectResponse((string) $session->url);
    }

    #[Route('/abonnement/success', name: 'app_membership_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $plan = $request->query->get('plan', 'annual');
        $sessionId = $request->query->get('session_id');
        $planLabel = $plan === 'annual' ? 'Offre Annuelle (89,99 €/an)' : 'Offre Mensuelle (9,99 €/mois)';

        // Vérifier la session Stripe et activer le compte premium
        if ($sessionId && $this->stripeConfig->isConfigured()) {
            Stripe::setApiKey($this->stripeConfig->getSecretKey());
            try {
                $session = StripeSession::retrieve($sessionId);
                $metadataUserId = $session->metadata['user_id'] ?? null;
                if ($session->payment_status === 'paid' && is_numeric($metadataUserId)) {
                    $user = $this->entityManager->getRepository(User::class)->find((int) $metadataUserId);
                    $currentUser = $this->getUser();
                    if ($user instanceof User && $currentUser instanceof User && $user->getId() === $currentUser->getId()) {
                        $user->setIsPremium(true);
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();
                    }
                }
            } catch (ApiErrorException) {
                // Ignorer les erreurs API : on affiche quand même la page succès
            }
        }

        return $this->render('membership/success.html.twig', [
            'plan_label' => $planLabel,
        ]);
    }

    #[Route('/abonnement/annule', name: 'app_membership_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        return $this->render('membership/cancel.html.twig');
    }
}
