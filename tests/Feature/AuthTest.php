<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_devolve_token_que_acessa_rotas_protegidas(): void
    {
        $user = User::factory()->create(['email' => 'ana@hotel.test']);

        $response = $this->postJson('/api/login', ['email' => 'ana@hotel.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'hotel_id']]);

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_senha_errada_retorna_401_sem_revelar_o_motivo(): void
    {
        User::factory()->create(['email' => 'ana@hotel.test']);

        $this->postJson('/api/login', ['email' => 'ana@hotel.test', 'password' => 'errada'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.');

        $this->postJson('/api/login', ['email' => 'naoexiste@hotel.test', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_exige_email_e_senha(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_rota_protegida_sem_token_retorna_401_em_json(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        // Sem o cabeçalho Accept: application/json também deve ser JSON, nunca redirecionamento.
        $this->get('/api/me')->assertUnauthorized();
    }

    public function test_logout_revoga_o_token(): void
    {
        User::factory()->create(['email' => 'ana@hotel.test']);
        $token = $this->postJson('/api/login', ['email' => 'ana@hotel.test', 'password' => 'password'])->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_tem_limite_de_tentativas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'x@hotel.test', 'password' => 'errada'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'x@hotel.test', 'password' => 'errada'])->assertStatus(429);
    }

    public function test_rota_inexistente_nao_vaza_detalhes_internos(): void
    {
        $this->getJson('/api/rota-que-nao-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }
}
