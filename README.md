# Hotel Reservas API

API REST em JSON para gestão de quartos e reservas de hotéis

- **Importador de XML** (`hotels.xml`, `rooms.xml`, `reserves.xml`) executado por cron (agendador do Laravel).
- **CRUD de quartos** e **POST de reserva**, com disponibilidade, cupom de desconto, taxa de serviço e pagamentos.
- **Segurança**: login com token (Sanctum), três papéis de usuário, limite de requisições.
- **Documentação OpenAPI 3.0.0** com Swagger UI em `/docs`, testes com PHPUnit e Docker (Laravel Sail).

Stack: PHP 8.4+ (o Sail usa 8.5), Laravel 12, MySQL 8.4, Sanctum, PHPUnit.

---

## 1. Como rodar (do zero)

Requisitos na sua máquina: **Git** e **Docker** com **Docker Compose 2.24 ou superior** (confira com `docker compose version`).

```bash
# 1. baixar o projeto
git clone https://github.com/Caio-Al/hotel-reservas-api.git
cd hotel-reservas-api

# 2. criar o .env (as configs de MySQL do Sail já vêm no .env.example)
cp .env.example .env

# 3. instalar as dependências (usa um container, não precisa de PHP instalado)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  laravelsail/php84-composer:latest composer install --ignore-platform-reqs

# 4. subir os containers (aplicação, MySQL e scheduler)
./vendor/bin/sail up -d

# 5. gerar a chave da aplicação e criar as tabelas
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

# 6. importar os XMLs (hotéis, quartos e reservas)
./vendor/bin/sail artisan hotel:import-xml

# 7. criar os usuários de teste e o cupom de exemplo
./vendor/bin/sail artisan db:seed
```

Para encurtar, crie o atalho `sail` (uma vez só):

```bash
echo "alias sail='sh \$([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.bashrc
source ~/.bashrc
```

A partir daqui os exemplos usam `sail`. Se não criou o atalho, troque por `./vendor/bin/sail`.

**Ordem importa:** rode a importação (passo 6) **antes** do seed (passo 7). O seed cria o gerente e a recepcionista ligados ao hotel 1, e esse hotel só existe depois da importação.

Resultado esperado do passo 6:

```
Hotéis: 3
Quartos: 6
Reservas: 6
Avisos: 1
```

O aviso é proposital: na reserva 6 do XML, uma diária (2022-12-03) cai fora do período da estadia (2022-10-01 a 2022-10-04). A reserva é importada mesmo assim e o aviso vai para o log.

Pronto. A API responde em `http://localhost` e a documentação interativa está em **http://localhost/docs**.

Para parar: `sail down`. Para apagar também o banco: `sail down -v`.

### Usuários de teste

Criados pelo `sail artisan db:seed` (senha de todos: `password`, **somente para desenvolvimento**).

| E-mail | Papel | Pode |
|---|---|---|
| `admin@hotel.test` | admin | tudo, em todos os hotéis |
| `gerente@hotel.test` | gerente do hotel 1 | cadastrar, alterar e remover quartos do hotel 1; operar reservas e pagamentos do hotel 1 |
| `recepcao@hotel.test` | recepcionista do hotel 1 | consultar quartos; operar reservas e pagamentos do hotel 1 |

Cupom de exemplo: `BEMVINDO10` (10% de desconto).

---

## 2. Como executar o cron (importação do XML)

O comando de importação é `hotel:import-xml`. Ele lê `hotels.xml`, `rooms.xml` e `reserves.xml` da pasta `database/xml/` e grava no banco. Pode rodar quantas vezes quiser: os registros são identificados pelo `id` do XML, então repetir **atualiza** em vez de duplicar. Tudo roda em uma transação (ou grava tudo, ou nada).

### Rodar na mão

```bash
sail artisan hotel:import-xml                     # usa a pasta padrão database/xml
sail artisan hotel:import-xml --path=outra/pasta  # outra pasta (relativa à raiz do projeto)
```

Dados estranhos no XML (diária fora do período, total diferente da soma das diárias, pagamentos acima do total) **não derrubam** a importação: viram um aviso no log.

### Agendamento automático

O agendamento está em `routes/console.php`: todos os dias às **02:00** (horário de `APP_TIMEZONE`, padrão `America/Bahia`), com `withoutOverlapping()`. A saída vai para `storage/logs/import.log`.

Dentro do Docker não existe o crontab do sistema, então o projeto tem um serviço chamado `scheduler` (arquivo `compose.override.yaml`) que roda `php artisan schedule:work`. Ele sobe junto com o `sail up -d`. Para conferir:

```bash
sail ps                          # o serviço "scheduler" deve estar "Up"
sail artisan schedule:list       # mostra hotel:import-xml com a próxima execução
sail logs --tail=20 scheduler    # saída do agendador
cat storage/logs/import.log      # saída das importações agendadas
```

Para ver o cron disparando sem esperar as 02:00, mude temporariamente `->dailyAt('02:00')` para `->everyMinute()` em `routes/console.php`, aguarde 1 minuto e olhe o `import.log`. Depois volte para `dailyAt('02:00')`.

### Em um servidor sem Docker

Basta uma linha no crontab do servidor, que dispara o agendador do Laravel a cada minuto:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. Como cadastrar um quarto (e usar a API)

Todas as rotas, menos o login, exigem o token no cabeçalho `Authorization: Bearer <token>`. As respostas são sempre JSON.

### 3.1 Fazer login e guardar o token

```bash
TOKEN=$(curl -s -X POST http://localhost/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"gerente@hotel.test","password":"password"}' \
  | sed -E 's/.*"token":"([^"]+)".*/\1/')
echo $TOKEN
```

O token vale por 24 horas. O login aceita 5 tentativas por minuto; as demais rotas, 60 por minuto (acima disso a API devolve 429).

### 3.2 Cadastrar um quarto

```bash
curl -s -X POST http://localhost/api/rooms \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"hotel_id":1,"name":"Room 3 Hotel 1"}'
```

Resposta `201`:

```json
{"data":{"id":7,"hotel_id":1,"name":"Room 3 Hotel 1","created_at":"...","updated_at":"..."}}
```

Nome repetido no **mesmo** hotel dá `422`; o mesmo nome em hotéis diferentes é permitido.

### 3.3 Demais operações do CRUD de quartos

```bash
curl -s http://localhost/api/rooms         -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"   # listar (paginado)
curl -s http://localhost/api/rooms/7       -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"   # ver um

curl -s -X PATCH http://localhost/api/rooms/7 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Suite 3 Hotel 1"}'                                                                           # alterar (PUT também funciona)

curl -s -o /dev/null -w "%{http_code}\n" -X DELETE http://localhost/api/rooms/7 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"                                           # remover: 204
```

Tentar remover um quarto que tem reservas (por exemplo o quarto 1) devolve `409`.

Quartos livres em um período:

```bash
curl -s "http://localhost/api/rooms/available?hotel_id=1&check_in=2030-01-10&check_out=2030-01-13" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

---

## 4. Como criar uma reserva

`POST /api/reserves` recebe o hotel, o quarto, o período, os hóspedes, **uma diária para cada noite** da estadia e, opcionalmente, cupom e pagamentos. O servidor calcula o preço.

```bash
D1=$(date -d "+10 days" +%F); D2=$(date -d "+11 days" +%F); D3=$(date -d "+12 days" +%F); OUT=$(date -d "+13 days" +%F)

curl -s -X POST http://localhost/api/reserves \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d "{
    \"hotel_id\": 1,
    \"room_id\": 2,
    \"check_in\": \"$D1\",
    \"check_out\": \"$OUT\",
    \"coupon_code\": \"BEMVINDO10\",
    \"guests\": [{\"name\":\"Maria\",\"last_name\":\"Silva\",\"phone\":\"5571999999999\"}],
    \"dailies\": [
      {\"date\":\"$D1\",\"value\":100},
      {\"date\":\"$D2\",\"value\":100},
      {\"date\":\"$D3\",\"value\":100}
    ],
    \"payments\": [{\"method\":1,\"value\":100}]
  }"
```

(No macOS, o `date -d` não existe: troque por `date -v+10d +%F`, e assim por diante, ou escreva as datas à mão no formato `AAAA-MM-DD`.)

Resposta `201` (resumida):

```json
{"data":{"id":7,"hotel_id":1,"room_id":2,"subtotal":"300.00","discount":"30.00","service_fee":"27.00","total":"297.00","coupon":"BEMVINDO10","paid":"100.00","balance":"197.00", "...":"..."}}
```

### Como o preço é calculado

```
subtotal = soma das diárias
desconto = cupom (percentual ou valor fixo, nunca maior que o subtotal)
taxa     = taxa de serviço (padrão 10%) sobre (subtotal - desconto)
total    = subtotal - desconto + taxa
```

No exemplo: 300,00 − 30,00 = 270,00; taxa de 10% = 27,00; total = **297,00**. Os valores ficam gravados na própria reserva, então mudar o cupom ou a taxa depois não altera reservas antigas. A conta é feita em centavos inteiros.

### Regras e erros

| Situação | Resposta |
|---|---|
| Quarto já reservado em qualquer noite do período | `409` |
| Cupom inexistente, inativo, vencido ou esgotado | `422` |
| Pagamentos informados somam mais que o total | `422` |
| Diárias não cobrem exatamente cada noite (check-in inclusive, check-out exclusivo) | `422` |
| Check-in no passado, estadia maior que 60 noites, quarto de outro hotel | `422` |
| Usuário sem permissão no hotel | `403` |
| Sem token ou token inválido | `401` |

### Outras rotas de reserva e pagamento

```bash
curl -s http://localhost/api/reserves       -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"   # listar
curl -s http://localhost/api/reserves/7     -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"   # ver

curl -s -X POST http://localhost/api/reserves/7/payments \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"method":1,"value":50}'                                   # registrar pagamento (não passa do saldo)

curl -s http://localhost/api/reserves/7/payments -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

### Mapa de endpoints

| Método | Rota | O que faz |
|---|---|---|
| POST | `/api/login` | login, devolve o token |
| POST | `/api/logout` | revoga o token atual |
| GET | `/api/me` | usuário logado (papel e hotel) |
| GET, POST | `/api/rooms` | listar, cadastrar |
| GET | `/api/rooms/available` | quartos livres no período |
| GET, PUT, PATCH, DELETE | `/api/rooms/{id}` | ver, alterar, remover |
| GET, POST | `/api/reserves` | listar, criar |
| GET | `/api/reserves/{id}` | ver |
| GET, POST | `/api/reserves/{id}/payments` | listar, registrar pagamento |

O contrato completo (parâmetros, corpos e erros) está em `docs/openapi.yaml` e pode ser testado em **http://localhost/docs** (clique em *Authorize* e cole o token). A página carrega o Swagger UI por CDN, então precisa de internet no navegador.

---

## 5. Testes automatizados

```bash
sail artisan test                          # suíte inteira (69 testes)
sail artisan test --filter=RoomApiTest     # só uma classe
```

Os testes usam SQLite em memória, sem tocar no seu MySQL. Cobrem repositórios, importador, autenticação, CRUD de quartos, preço, reservas, pagamentos e a documentação.

Observação: o SQLite ignora `lockForUpdate`, então o travamento de linha (que impede duas reservas simultâneas do mesmo quarto) só é exercitado de verdade no MySQL.

---

## 6. Logs

Um arquivo por dia em `storage/logs/laravel-AAAA-MM-DD.log` (guarda 14 dias). Registram login e falha de login (id/e-mail e IP, nunca senha ou token), importações, quartos, reservas, pagamentos e regras de negócio violadas (`warning`). A saída do cron fica em `storage/logs/import.log`.

```bash
tail -f storage/logs/laravel-$(date +%F).log
```

---

## 7. Arquitetura e decisões

Camadas: **Controller** (recebe a requisição e devolve JSON) → **Service** (regras de negócio) → **Repository** (acesso ao banco, atrás de interfaces injetadas). Autorização por Policies; validação por Form Requests; formato de saída por API Resources.

Premissas, já que o desafio não define:

- **Taxa de serviço** de 10%, configurável em `HOTEL_SERVICE_FEE_PERCENT` (`config/hotel.php`). Juros não foram implementados.
- **Disponibilidade**: cada quarto é uma unidade e não pode ter duas reservas que se sobreponham. O XML não tem tipos de quarto nem quantidades.
- **Pagamento**: o XML manda a forma de pagamento como código numérico sem legenda, então `payments.method` guarda o número como veio.
- **XML**: tratado como saída de outro sistema, por isso a importação é repetível. Os XMLs do desafio são estáticos, então a execução diária não traz dados novos.
- **Fuso horário**: `America/Bahia`. Datas como "hoje" (validação do check-in, validade do cupom) e o horário do cron seguem o horário local do hotel.

Banco de dados: o diagrama está em `docs/database/hotel_reservas.png` (modelo do Workbench em `docs/database/hotel_reservas.mwb`).

---

## 8. Documentação do projeto

| Arquivo | Conteúdo |
|---|---|
| `docs/PROCESSO.md` | passo a passo do desenvolvimento, decisões e problemas encontrados |
| `docs/openapi.yaml` | contrato OpenAPI 3.0.0 (também em `/docs`) |
| `docs/GIT.md` | padrão de branches, commits e versionamento |
| `docs/database/` | diagrama do banco (`.png` e `.mwb`) |

Git: Conventional Commits, uma branch por tarefa e merge com `--no-ff`. Versão entregue: tag `v1.0.0`.