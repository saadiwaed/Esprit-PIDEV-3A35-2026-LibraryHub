<?php

namespace App\Tests\Service\Forum;

use App\Service\Forum\ForumAiAssistantService;
use App\Service\Forum\LlmAiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ForumAiAssistantServiceTest extends TestCase
{
    public function testGenerateCommunityDraftUsesFallbackWhenInputTooShort(): void
    {
        $service = $this->buildServiceWithDisabledProvider();

        $result = $service->generateCommunityDraft('A', 'B');

        self::assertFalse($result->usedAi());
        self::assertTrue($result->isFallbackUsed());
        self::assertArrayHasKey('purpose', $result->getPayload());
        self::assertArrayHasKey('description', $result->getPayload());
        self::assertArrayHasKey('rules', $result->getPayload());
        self::assertArrayHasKey('welcomeMessage', $result->getPayload());
    }

    public function testImprovePostDraftUsesAiWhenConfidenceIsHigh(): void
    {
        $service = $this->buildServiceWithAiPayload([
            'title' => 'Titre IA propose',
            'content' => 'Contenu structure par IA.',
            'confidence' => 0.9,
        ]);

        $result = $service->improvePostDraft(
            'Titre initial',
            'Voici un brouillon suffisamment long pour lancer le traitement.',
            'Club Lecture'
        );

        self::assertTrue($result->usedAi());
        self::assertFalse($result->isFallbackUsed());
        self::assertSame('Titre IA propose', $result->getPayload()['title']);
        self::assertSame('Contenu structure par IA.', $result->getPayload()['content']);
    }

    public function testSuggestCommentUsesFallbackWhenProviderUnavailable(): void
    {
        $service = $this->buildServiceWithDisabledProvider();

        $result = $service->suggestComment(
            'Mon sujet',
            'Contenu du post avec assez de texte pour depasser le minimum.',
            ['Premier commentaire', 'Deuxieme commentaire'],
            ''
        );

        self::assertFalse($result->usedAi());
        self::assertTrue($result->isFallbackUsed());
        self::assertArrayHasKey('suggestion', $result->getPayload());
        self::assertNotSame('', $result->getPayload()['suggestion']);
    }

    public function testPostFallbackRewritesExistingDraftWhenProviderUnavailable(): void
    {
        $service = $this->buildServiceWithDisabledProvider();
        $initialTitle = 'Magie et science-fiction';
        $initialContent = 'Je parle des systemes de magie dans les romans de science-fiction et je veux des avis.';

        $result = $service->improvePostDraft($initialTitle, $initialContent, 'Explorateurs');

        self::assertFalse($result->usedAi());
        self::assertTrue($result->isFallbackUsed());
        self::assertArrayHasKey('title', $result->getPayload());
        self::assertArrayHasKey('content', $result->getPayload());
        self::assertSame($initialTitle, $result->getPayload()['title']);
        self::assertStringContainsString($initialContent, $result->getPayload()['content']);
        self::assertStringContainsString('Pour aller plus loin, vous pouvez reagir a:', $result->getPayload()['content']);
        self::assertStringNotContainsString('Contexte:', $result->getPayload()['content']);
        self::assertNotSame($initialContent, $result->getPayload()['content']);
    }

    public function testPostFallbackDoesNotDuplicateAppendixOnRepeatedCalls(): void
    {
        $service = $this->buildServiceWithDisabledProvider();
        $initialContent = 'J adore la science-fiction. Quel roman ou film vous a le plus marque ?';

        $first = $service->improvePostDraft('Voyage au-dela des etoiles', $initialContent, 'Explorateurs');
        $second = $service->improvePostDraft(
            'Voyage au-dela des etoiles',
            (string) $first->getPayload()['content'],
            'Explorateurs'
        );

        self::assertSame(1, substr_count((string) $second->getPayload()['content'], 'Pour aller plus loin, vous pouvez reagir a:'));
    }

    public function testPostFallbackCleansLegacyStructuredFallbackBeforeRegenerating(): void
    {
        $service = $this->buildServiceWithDisabledProvider();
        $legacyFallback = implode("\n\n", [
            'Contexte:',
            'J adore comment la science-fiction nous transporte dans des univers inconnus et imagine des technologies incroyables. Quel est votre roman ou film prefere qui vous a fait rever au futur ?',
            'Point principal:',
            'Je souhaite lancer une discussion constructive et recueillir des retours pratiques.',
            'Questions pour la communaute:',
            '- Quel est votre point de vue principal sur ce sujet ?',
            '- Quels exemples concrets ou ressources recommandez-vous ?',
        ]);

        $result = $service->improvePostDraft('Voyage au-dela des etoiles', $legacyFallback, 'Explorateurs');
        $content = (string) $result->getPayload()['content'];

        self::assertFalse($result->usedAi());
        self::assertTrue($result->isFallbackUsed());
        self::assertStringContainsString('J adore comment la science-fiction nous transporte', $content);
        self::assertStringContainsString('Pour aller plus loin, vous pouvez reagir a:', $content);
        self::assertSame(1, substr_count($content, 'Pour aller plus loin, vous pouvez reagir a:'));
        self::assertStringNotContainsString('Point principal:', $content);
        self::assertStringNotContainsString('Questions pour la communaute:', $content);
    }

    public function testSummarizeThreadFallsBackWhenConfidenceIsLow(): void
    {
        $service = $this->buildServiceWithAiPayload([
            'summary' => 'Resume court.',
            'keyPoints' => ['Point 1'],
            'disagreements' => ['Desaccord 1'],
            'openQuestions' => ['Question 1'],
            'confidence' => 0.2,
        ]);

        $result = $service->summarizeThread(
            'Discussion test',
            'Contenu principal du sujet avec suffisamment de matiere.',
            ['Commentaire 1', 'Commentaire 2']
        );

        self::assertFalse($result->usedAi());
        self::assertTrue($result->isFallbackUsed());
        self::assertArrayHasKey('summary', $result->getPayload());
        self::assertArrayHasKey('keyPoints', $result->getPayload());
        self::assertArrayHasKey('openQuestions', $result->getPayload());
    }

    /**
     * @param array<string, mixed> $aiPayload
     */
    private function buildServiceWithAiPayload(array $aiPayload): ForumAiAssistantService
    {
        $llmClient = new LlmAiClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode($aiPayload, JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE)),
            ]),
            new NullLogger(),
            true,
            'openai',
            'https://example.test/v1/chat/completions',
            'test-key',
            'test-model',
            5.0,
            0.3,
            500
        );

        return new ForumAiAssistantService($llmClient, new NullLogger(), 12, 0.55, 10);
    }

    private function buildServiceWithDisabledProvider(): ForumAiAssistantService
    {
        $llmClient = new LlmAiClient(
            new MockHttpClient(),
            new NullLogger(),
            false,
            'openai',
            '',
            '',
            '',
            5.0,
            0.3,
            500
        );

        return new ForumAiAssistantService($llmClient, new NullLogger(), 12, 0.55, 10);
    }
}
