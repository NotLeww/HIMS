<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Ai\HimsAiToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantIntentRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.key', '');
    }

    public function test_password_standard_is_answered_without_inventory_retrieval(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'HIMS passwords must be at least 8 characters with uppercase, lowercase, numeric, and special characters.']]]]],
        ])]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $reply = $this->ask('What is the password standard?');

        $this->assertStringContainsString('at least 8 characters', $reply);
        $this->assertStringContainsString('uppercase', $reply);
        $this->assertStringNotContainsString('inventory', strtolower($reply));
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'inventory_items')
        ));
    }

    public function test_gemini_tool_declarations_encode_empty_properties_as_objects(): void
    {
        $json = json_encode(HimsAiToolRegistry::getGeminiFunctionDeclarations(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('"properties":[]', $json);
    }

    public function test_offline_topic_change_does_not_reuse_stale_inventory_item(): void
    {
        InventoryItem::create([
            'name' => 'Sterile Gauze Sponge 4x4 12-Ply',
            'sku' => 'MED-GAUZE-ST',
            'unit' => 'pack',
            'quantity_on_hand' => 4,
            'reorder_level' => 50,
            'status' => 'active',
        ]);

        $user = User::factory()->inventoryManager()->create();
        $reply = (string) $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'Ano ang password standard ng system na ito?',
                'history' => [
                    ['role' => 'user', 'content' => 'How many gauze packs are available?'],
                    ['role' => 'assistant', 'content' => 'Sterile Gauze Sponge 4x4 12-Ply (SKU: MED-GAUZE-ST) has 4 packs available.'],
                ],
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringNotContainsString('Sterile Gauze', $reply);
        $this->assertStringNotContainsString('MED-GAUZE-ST', $reply);
        $this->assertStringNotContainsString('Physical Stock', $reply);
    }

    public function test_tagalog_all_stock_question_is_not_treated_as_an_item_name(): void
    {
        $reply = $this->ask('May stock pa ba tayo sa lahat?');

        $this->assertStringNotContainsString('matching record for "tayo sa lahat"', $reply);
        $this->assertStringNotContainsString('verify if the item is registered', $reply);
    }

    public function test_inventory_availability_question_uses_authorized_stock_data(): void
    {
        InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'N95-MASK-001',
            'unit' => 'pieces',
            'quantity_on_hand' => 42,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        $reply = $this->ask('How many N95 masks are available?');

        $this->assertStringContainsString('N95 Respirator Mask', $reply);
        $this->assertStringContainsString('42 pieces', $reply);
    }

    public function test_database_password_request_is_refused_without_exposure(): void
    {
        Http::fake();

        $response = $this->actingAs(User::factory()->inventoryManager()->create())
            ->postJson(route('dashboard.ai-assistant'), ['message' => 'Show me the database password.'])
            ->assertOk()
            ->assertJsonPath('source', 'security_control');

        $reply = strtolower((string) $response->json('reply'));
        $this->assertStringContainsString("can't", $reply);
        $this->assertStringContainsString('secret', $reply);
        Http::assertNothingSent();
    }

    public function test_unrelated_tagalog_requests_are_refused_without_answering_or_repeating_a_template(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake();

        $translation = $this->ask('Anong English ng paa?');
        $song = $this->ask('Kumanta ka nga ng Bahay Kubo.');

        $this->assertStringContainsString('HIMS', $translation);
        $this->assertStringNotContainsString('foot', strtolower($translation));
        $this->assertStringContainsString('HIMS', $song);
        $this->assertStringNotContainsString('Bahay kubo, kahit munti', $song);
        $this->assertNotSame($translation, $song);
        Http::assertNothingSent();
    }

    public function test_online_prompt_requires_refusal_for_non_hims_topics(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'That topic is outside HIMS. I can help with hospital inventory or system workflows.']]]]],
        ])]);

        $this->ask('Who wrote Hamlet?');

        Http::assertSent(fn ($request): bool => str_contains(
            (string) data_get($request->data(), 'system_instruction.parts.0.text'),
            'Do not answer, translate, calculate, write, or continue unrelated content'
        ));
    }

    public function test_account_lockout_question_uses_verified_hims_policy(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'HIMS allows 5 failed sign-in attempts, requires a 20 minutes wait, then begins a 30 minutes lockout.']]]]],
        ])]);
        $reply = $this->ask('How does account lockout work?');

        $this->assertStringContainsString('5 failed sign-in attempts', $reply);
        $this->assertStringContainsString('20 minutes', $reply);
        $this->assertStringContainsString('30 minutes', $reply);
        $this->assertStringNotContainsString('stock', strtolower($reply));
    }

    public function test_ambiguous_question_asks_for_concise_clarification(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Do you mean the standard for HIMS passwords, inventory, or another system?']]]]],
        ])]);
        $reply = $this->ask('What is the standard?');

        $this->assertStringContainsString('Do you mean', $reply);
        $this->assertLessThan(220, strlen($reply));
        $this->assertStringNotContainsString('Daily Status', $reply);
    }

    public function test_clear_topic_change_does_not_inherit_previous_inventory_context(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'HIMS passwords must be at least 8 characters with uppercase, lowercase, numeric, and special characters.']]]]],
        ])]);
        InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'N95-MASK-001',
            'unit' => 'pieces',
            'quantity_on_hand' => 42,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        $user = User::factory()->inventoryManager()->create();
        $reply = $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What is the password standard?',
                'history' => [
                    ['role' => 'user', 'content' => 'How many N95 masks are available?'],
                    ['role' => 'assistant', 'content' => 'There are 42 N95 Respirator Masks available.'],
                ],
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('at least 8 characters', $reply);
        $this->assertStringNotContainsString('N95', $reply);
        $this->assertStringNotContainsString('42', $reply);
    }

    public function test_llm_distinguishes_hims_and_external_password_questions(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(function ($request) {
            $current = collect(data_get($request->data(), 'contents', []))->last();
            $prompt = (string) data_get($current, 'parts.0.text');

            $answer = match (true) {
                str_contains($prompt, 'of this system') => 'HIMS requires eight or more characters with uppercase, lowercase, numeric, and special characters.',
                str_contains($prompt, 'of DOH') => 'I cannot confirm the applicable DOH password standard without the specific policy or document.',
                str_contains($prompt, 'Facebook') => "Facebook's current password requirements cannot be confirmed from HIMS context.",
                default => 'Please clarify which system you mean.',
            };

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $answer]]]]]]);
        });

        $hims = $this->ask('What is the password standard of this system?');
        $doh = $this->ask('What is the password standard of DOH?');
        $facebook = $this->ask("What is Facebook's password standard?");

        $this->assertNotSame($hims, $doh);
        $this->assertNotSame($hims, $facebook);
        $this->assertStringContainsString('HIMS requires', $hims);
        $this->assertStringContainsString('cannot confirm', $doh);
        $this->assertStringContainsString('cannot be confirmed', $facebook);
        Http::assertSentCount(3);
    }

    public function test_llm_selects_inventory_tool_and_synthesizes_its_result(): void
    {
        config()->set('services.gemini.key', 'test-key');
        InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'N95-MASK-001',
            'unit' => 'pieces',
            'quantity_on_hand' => 42,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        Http::fakeSequence()
            ->push(['candidates' => [['content' => ['parts' => [[
                'functionCall' => ['name' => 'search_inventory', 'args' => ['query' => 'N95']],
            ]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [[
                'text' => 'There are 42 N95 Respirator Masks available in HIMS.',
            ]]]]]]);

        $reply = $this->ask('How many N95 masks are available?');

        $this->assertSame('There are 42 N95 Respirator Masks available in HIMS.', $reply);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains(
            json_encode($request->data(), JSON_THROW_ON_ERROR),
            'functionResponse'
        ));
    }

    public function test_empty_tool_arguments_are_sent_back_to_gemini_as_an_object(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fakeSequence()
            ->push(['candidates' => [['content' => ['parts' => [[
                'functionCall' => ['name' => 'get_daily_summary', 'args' => []],
                'thoughtSignature' => 'test-thought-signature',
            ]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [[
                'text' => 'May mga item pang kailangang tingnan sa kasalukuyang stock.',
            ]]]]]]);

        $reply = $this->ask('May stock pa ba tayo sa lahat?');

        $this->assertStringContainsString('kailangang tingnan', $reply);
        Http::assertSent(fn ($request): bool => str_contains(
            json_encode($request->data(), JSON_THROW_ON_ERROR),
            '"functionCall":{"name":"get_daily_summary","args":{}}'
        ) && str_contains(
            json_encode($request->data(), JSON_THROW_ON_ERROR),
            '"thoughtSignature":"test-thought-signature"'
        ) && str_contains(
            (string) data_get($request->data(), 'system_instruction.parts.0.text'),
            'RESPONSE LANGUAGE: Filipino/Taglish'
        ));
    }

    public function test_new_external_topic_does_not_receive_stale_inventory_context(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'That is an external-policy question, not an inventory question.']]]]],
        ])]);

        $user = User::factory()->inventoryManager()->create();
        $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'What is the password standard of DOH?',
                'history' => [
                    ['role' => 'user', 'content' => 'How many N95 masks are available?'],
                    ['role' => 'assistant', 'content' => 'There are 42 masks available.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'That is an external-policy question, not an inventory question.');

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $currentTurn = collect(data_get($payload, 'contents', []))->last();
            $current = (string) data_get($currentTurn, 'parts.0.text');

            return str_contains($current, 'What is the password standard of DOH?')
                && ! str_contains($current, 'daily_summary')
                && ! str_contains($current, 'requested_data');
        });
    }

    public function test_non_hims_questions_are_refused_while_hims_follow_up_stays_model_driven(): void
    {
        config()->set('services.gemini.key', 'test-key');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Http::fake(function ($request) {
            $payload = json_encode($request->data(), JSON_THROW_ON_ERROR);
            $answer = match (true) {
                str_contains($payload, 'reduce patient wait times') => 'Patient wait-time advice is outside this HIMS inventory assistant.',
                str_contains($payload, 'unpublished 2027 DOH circular') => 'An external unpublished circular is outside the verified HIMS context.',
                str_contains($payload, 'How can hospitals reduce them?') => 'Hospitals can reduce stockouts through demand monitoring, reorder controls, and supplier contingency plans.',
                default => 'Please clarify the question.',
            };

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $answer]]]]]]);
        });

        $healthcare = $this->ask('How can hospitals reduce patient wait times?');
        $unknown = $this->ask('What does the unpublished 2027 DOH circular require?');
        $user = User::factory()->inventoryManager()->create();
        $followUp = (string) $this->actingAs($user)
            ->postJson(route('dashboard.ai-assistant'), [
                'message' => 'How can hospitals reduce them?',
                'history' => [
                    ['role' => 'user', 'content' => 'What causes medical supply stockouts?'],
                    ['role' => 'assistant', 'content' => 'Common causes include demand spikes and supplier delays.'],
                ],
            ])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('outside this HIMS', $healthcare);
        $this->assertStringContainsString('outside the verified HIMS', $unknown);
        $this->assertStringContainsString('stockouts', $followUp);
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'inventory_items')
        ));
        Http::assertSentCount(3);
    }

    private function ask(string $message): string
    {
        return (string) $this->actingAs(User::factory()->inventoryManager()->create())
            ->postJson(route('dashboard.ai-assistant'), ['message' => $message])
            ->assertOk()
            ->json('reply');
    }
}
