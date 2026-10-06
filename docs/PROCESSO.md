# Processo de desenvolvimento

## Configuração do ambiente

### Verificação do Docker

Comandos executados:

```bash
docker --version
docker compose version
docker run --rm hello-world
git --version

*criei o arquivo laravel

curl -s "https://laravel.build/hotel-reservas-api?with=mysql" | bash
cd hotel-reservas-api
./vendor/bin/sail up -d

*fiz um atalho pro sail

echo "alias sail='sh \$([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.bashrc
source ~/.bashrc

*habilitei a api ja que n vem por padrão

sail artisan install:api

*preparando o ambiente git e dando o primeiro commit

git init -b main
git add .
git commit -m "chore: inicia projeto Laravel com Sail"
git remote add origin git@github.com:Caio-Al/hotel-reservas-api.git
git push -u origin main

*colocando o nome n migrate de hotel e criando o model de hotal 

*criando o make:model de Room
*criando o make:model de Reserve
*criando o make:model de Guest
*criando o make:model de Daily
*criando o make:model de Payment

*em ordem para n comprometer a ordem de cadastro
```


## Passo 2: migrations e models (base do banco)

### Por que estas escolhas

O banco foi modelado a partir dos três XMLs do desafio: cada elemento virou uma tabela (`Hotel` -> `hotels`, `Room` -> `rooms`, `Reserve` -> `reserves`, e `Guest`, `Daily` e `Payment` como tabelas filhas da reserva). As tabelas foram criadas por migrations, e não por SQL solto, para o esquema ficar versionado junto com o código e qualquer pessoa recriar o banco com `sail artisan migrate`.

- **Ordem de criação**: os models foram gerados um de cada vez (`hotels`, `rooms`, `reserves`, `guests`, `dailies`, `payments`). As migrations rodam pela data no nome do arquivo, e uma chave estrangeira precisa que a tabela referenciada já exista.
- **`restrictOnDelete` em `reserves`**: o banco recusa apagar um hotel ou quarto que tenha reservas, em vez de apagar o histórico em silêncio.
- **`cascadeOnDelete` nos filhos da reserva**: hóspedes, diárias e pagamentos só existem dentro da reserva; apagou a reserva, somem junto.
- **Índice composto `(room_id, check_in, check_out)`** em `reserves`: acelera a consulta de disponibilidade usada mais adiante.
- **Dinheiro como `decimal(10,2)`**, nunca `float`, para não ter erro de arredondamento.
- **`payments.method` como `unsignedSmallInteger`**: o XML envia a forma de pagamento como código numérico e o desafio não explica o significado dos códigos. O código é guardado como veio, sem tradução, para não inventar dado. Isso entra nas premissas do README.
- **`id` no `$fillable`** de `Hotel`, `Room` e `Reserve`: o importador usa os ids do XML.

### O que foi feito

- 6 migrations e 6 models com `$fillable`, `casts()` (datas e decimais) e os relacionamentos (`hasMany` e `belongsTo`).
- Verificação de sintaxe dos models com `sail php -l` e correção dos arquivos que tinham erro.

### Comandos executados

```bash
sail artisan make:model Room -m
sail artisan make:model Reserve -m
sail artisan make:model Guest -m
sail artisan make:model Daily -m
sail artisan make:model Payment -m
sail artisan migrate
sail artisan migrate:status
sail artisan db:table reserves
for f in app/Models/*.php; do sail php -l "$f"; done
```

### Como foi testado (manualmente, no tinker)

1. `migrate:status` mostrando todas as migrations como executadas.
2. `db:table reserves` conferindo colunas, índice composto e as duas chaves estrangeiras.
3. Cadeia completa no `tinker`: hotel -> quarto (pelo relacionamento) -> reserva -> hóspede, diárias e pagamento, lida de volta com `load()` e `toArray()`.
4. Erros provocados de propósito: quarto com `hotel_id` inexistente (falha pela chave estrangeira) e remoção de quarto com reserva (falha por causa do `restrictOnDelete`).

Os dados de teste são descartados com `sail artisan migrate:fresh`. No tinker, o paginador do terminal (`(END)`) é fechado com a tecla `q`.
