<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AccountActivationChallenge;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Notifications\PasswordResetOtp;
use App\Services\AccountActivationService;
use App\Services\PasswordHistoryService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PerUserPasswordHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_PASSWORD = 'SharedPassword1!';

    private const CURRENT_PASSWORD = 'CurrentSecure1!';

    public static function authenticationPanels(): array
    {
        return [
            'staff' => ['staff', AuthenticationContext::WEB_GUARD],
            'admin' => ['admin', AuthenticationContext::ADMIN_GUARD],
            'super admin' => ['super-admin', AuthenticationContext::SUPER_ADMIN_GUARD],
        ];
    }

    #[DataProvider('authenticationPanels')]
    public function test_every_role_is_blocked_from_reusing_its_own_password(
        string $panel,
        string $guard,
    ): void {
        $user = $this->userForPanel($panel, self::SHARED_PASSWORD);
        $this->recordCurrentPassword($user, now()->subYears(3));
        $user->forceFill(['password' => self::CURRENT_PASSWORD])->save();
        $originalHash = $user->password;

        $this->actingAs($user, $guard)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => self::SHARED_PASSWORD,
                'password_confirmation' => self::SHARED_PASSWORD,
            ])->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('updatePassword', [
                'password' => PasswordHistoryService::REJECTION_MESSAGE,
            ]);

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_profile_changes_are_recorded_and_an_old_password_stays_blocked(): void
    {
        $user = User::factory()->warehouseStaff()->create(['password' => self::CURRENT_PASSWORD]);
        $this->recordCurrentPassword($user);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => 'CompletelyNew2!',
                'password_confirmation' => 'CompletelyNew2!',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('CompletelyNew2!', $user->fresh()->password));
        $this->assertSame(2, PasswordHistory::query()->count());

        $this->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'CompletelyNew2!',
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' => self::CURRENT_PASSWORD,
        ])->assertSessionHasErrorsIn('updatePassword', [
            'password' => PasswordHistoryService::REJECTION_MESSAGE,
        ]);
    }

    public function test_account_activation_can_use_another_accounts_password(): void
    {
        $formerUser = User::factory()->create([
            'password' => self::SHARED_PASSWORD,
            'status' => UserStatus::Inactive,
        ]);
        $this->recordCurrentPassword($formerUser, now()->subYears(5));

        $pending = User::factory()->create([
            'email' => 'managed@example.test',
            'password' => null,
            'status' => UserStatus::PendingActivation,
            'email_verified_at' => now(),
        ]);
        AccountActivationChallenge::query()->create([
            'user_id' => $pending->id,
            'channel' => 'email',
            'verified_at' => now(),
        ]);

        $activated = app(AccountActivationService::class)->complete($pending, self::SHARED_PASSWORD);

        $this->assertNotNull($activated);
        $this->assertSame(UserStatus::Active, $activated->status);
        $this->assertTrue(Hash::check(self::SHARED_PASSWORD, $activated->password));
    }

    public function test_password_reset_can_use_another_users_password(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['password' => self::SHARED_PASSWORD]);
        $this->recordCurrentPassword($owner);
        $account = User::factory()->warehouseStaff()->create(['password' => self::CURRENT_PASSWORD]);
        $originalHash = $account->password;

        $this->post(route('password.email'), ['email' => $account->email]);
        $otp = Notification::sent($account, PasswordResetOtp::class)->firstOrFail()->otp;
        $verification = $this->post(route('password.otp.verify'), [
            'email' => $account->email,
            'otp' => $otp,
        ]);
        $token = basename((string) parse_url($verification->headers->get('Location'), PHP_URL_PATH));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $account->email,
            'password' => self::SHARED_PASSWORD,
            'password_confirmation' => self::SHARED_PASSWORD,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertNotSame($originalHash, $account->fresh()->password);
        $this->assertTrue(Hash::check(self::SHARED_PASSWORD, $account->fresh()->password));
    }

    public function test_expired_password_replacement_can_use_another_users_password(): void
    {
        $owner = User::factory()->create(['password' => self::SHARED_PASSWORD]);
        $this->recordCurrentPassword($owner);
        $expired = User::factory()->warehouseStaff()->create([
            'password' => self::CURRENT_PASSWORD,
            'password_changed_at' => now()->subDays(90),
        ]);

        $this->post(route('login'), [
            'email' => $expired->email,
            'password' => self::CURRENT_PASSWORD,
        ])->assertRedirect(route('password.expired'));

        $this->from(route('password.expired'))->put(route('password.expired.update'), [
            'password' => self::SHARED_PASSWORD,
            'password_confirmation' => self::SHARED_PASSWORD,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($expired);
        $expired->refresh();
        $this->assertTrue(Hash::check(self::SHARED_PASSWORD, $expired->password));
        $this->assertFalse($expired->passwordHasExpired());
    }

    public function test_password_histories_are_completely_isolated_per_account(): void
    {
        $passwords = app(PasswordHistoryService::class);
        $createUser = fn (string $email): callable => fn (string $hash): User => User::factory()->create([
            'email' => $email,
            'password' => $hash,
        ]);
        $changePassword = fn (User $user): callable => function (string $hash) use ($user): User {
            $user->forceFill(['password' => $hash])->save();

            return $user;
        };

        $userA = $passwords->usePassword(null, self::SHARED_PASSWORD, $createUser('user-a@example.test'));
        $passwords->usePassword($userA, self::CURRENT_PASSWORD, $changePassword($userA));

        try {
            $passwords->usePassword($userA, self::SHARED_PASSWORD, $changePassword($userA));
            $this->fail('User A reused a password from User A history.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                PasswordHistoryService::REJECTION_MESSAGE,
                $exception->errors()['password'][0],
            );
        }

        $userB = $passwords->usePassword(null, self::SHARED_PASSWORD, $createUser('user-b@example.test'));
        $passwords->usePassword($userB, 'UserBReplacement2!', $changePassword($userB));

        try {
            $passwords->usePassword($userB, self::SHARED_PASSWORD, $changePassword($userB));
            $this->fail('User B reused a password from User B history.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                PasswordHistoryService::REJECTION_MESSAGE,
                $exception->errors()['password'][0],
            );
        }

        $userAHistory = PasswordHistory::query()->whereBelongsTo($userA)->orderBy('id')->get();
        $userBHistory = PasswordHistory::query()->whereBelongsTo($userB)->orderBy('id')->get();

        $this->assertCount(2, $userAHistory);
        $this->assertCount(2, $userBHistory);
        $this->assertSame(
            $userAHistory->first()->password_fingerprint,
            $userBHistory->first()->password_fingerprint,
        );
        $this->assertTrue($userAHistory->every(fn (PasswordHistory $history) => $history->user_id === $userA->id));
        $this->assertTrue($userBHistory->every(fn (PasswordHistory $history) => $history->user_id === $userB->id));
    }

    public function test_new_password_and_history_record_roll_back_together_on_failure(): void
    {
        $user = User::factory()->create(['password' => self::CURRENT_PASSWORD]);
        $originalHash = $user->password;

        try {
            app(PasswordHistoryService::class)->usePassword(
                $user,
                'RollbackCandidate3!',
                function (string $passwordHash) use ($user): User {
                    $user->forceFill(['password' => $passwordHash])->save();

                    throw new RuntimeException('Simulated failure after the password write.');
                },
            );

            $this->fail('The simulated failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after the password write.', $exception->getMessage());
        }

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseCount('password_histories', 0);
    }

    public function test_history_serialization_and_rejection_do_not_disclose_security_details(): void
    {
        $owner = User::factory()->create(['password' => self::SHARED_PASSWORD]);
        $history = $this->recordCurrentPassword($owner);
        $owner->forceFill(['password' => self::CURRENT_PASSWORD])->save();

        $this->assertArrayNotHasKey('password_hash', $history->toArray());
        $this->assertArrayNotHasKey('password_fingerprint', $history->toArray());

        $response = $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => self::CURRENT_PASSWORD,
                'password' => self::SHARED_PASSWORD,
                'password_confirmation' => self::SHARED_PASSWORD,
            ]);

        $response->assertSessionHasErrorsIn('updatePassword', [
            'password' => PasswordHistoryService::REJECTION_MESSAGE,
        ]);
        $response->assertSessionHas('_old_input', fn (array $input): bool => ! array_key_exists('password', $input));
        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(PasswordHistoryService::REJECTION_MESSAGE)
            ->assertDontSee($history->password_hash);
        $this->assertStringNotContainsString($history->password_hash, (string) $response->getContent());
    }

    private function recordCurrentPassword(User $user, mixed $usedAt = null): PasswordHistory
    {
        return PasswordHistory::query()->create([
            'user_id' => $user->getKey(),
            'password_hash' => $user->password,
            'used_at' => $usedAt ?? now(),
        ]);
    }

    private function userForPanel(string $panel, string $password): User
    {
        return match ($panel) {
            'admin' => User::factory()->administrator()->create(['password' => $password]),
            'super-admin' => User::factory()->superAdministrator()->create(['password' => $password]),
            default => User::factory()->warehouseStaff()->create(['password' => $password]),
        };
    }

    /** @return array<string, string> */
    private function userPayload(string $password): array
    {
        return [
            'surname' => 'Managed',
            'first_name' => 'Account',
            'email' => 'managed@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'viewer',
            'department' => 'Administration',
            'phone' => '09171234567',
        ];
    }
}
