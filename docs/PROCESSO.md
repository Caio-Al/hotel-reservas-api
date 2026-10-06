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

```

### migrations e models (base do banco)

usei os arquivos dos XMLs pra modelar o banco, cada elemento virou uma tabela(hotel, Room, Reserve, guest, Daily e payment) as tabelas foram criadas com migrations pro esquema ficar versionado junto com o codigo e tiver como recriar o banco com o "sail artisan migrate"

a ordem de criação foi 1 por vem na ordem dos hotels, rooms, reserves, guest, dailies e payments, as migrations rodam pela data do nome do arquivo e uma chave estrangeira precisa que a tabela referencia ja exista, 

coloquei restrictOnDelete em reserves o banco recusa apgar um hotel ou quarto que tenha reservas, em vez de apagar o historico em silencio 

o cascadeOnDelete nos filhos da reserva, hospedes, diarias e pagamentos so existem dentro da reserva, então se apgou a reserva somem juntos

o indice composto em reserves acelera a consulta de disponibilidade usada 

o paymentes.method como unsignedSmallInteger pq o XML envia a froma de pagamento como codigo numerico e o desafio n explica os significados dos codigos, netão, preferi guardar o codigo como veio, ae para caso fosse informado, daria para criar uma tabela depois

### o que fiz

- 6 migrations e 6 models com `$fillable`, `casts()` (datas e decimais) e os relacionamentos (`hasMany` e `belongsTo`).
- Verificação de sintaxe dos models com `sail php -l` e correção dos arquivos que tinham erro.

### como testei

testei manualmente pelo tinker, coloquei uma situação completa, como hotel, quarto, reserva, hóspede, diarias e pagamentos, e na hora de retornar os dados fiz erros de proposito pra verificar como comportava, com id inexistente de hotel e remoção de quarto com reserva. 

### aquitetura e ambiente de testes

a arquitetura em camadas que estou implementando é com controller, Service e Repository

o motivo é no geral, para separação de responsabilidades e testabilidade, separar oq interage com o banco com oq faz requisições, trabalhei assim com projetos na faculdade, e no estagio segui o mesmo padrão, então repliquei aqui

o controller recebe requisição, chama o service, devolve Json e o status, não tem regra de negocio

o service concentra as regras, como disponibilidade, preço, cupom e pagamento

o repository é onde tem a a conecção de fato com o banco, onde se aplica a busca, inserções, modificações e deletes do banco

nessa parte, implementei a camada de repository(interfaces, implementações e Eloquent) o service e controller vou implementar junto com as rotas

### testes 

fiz os testes usando o SQL lite porque são rapidos e isolados, porem o SQLite ignora `lockForUpdate`, então o travamento de linha só é validado no MySQL real.

durante os testes teve um bug do date no cast, pq no SQLite é gravado a data com hora, oq quebra a regra de check-in e checkou no mesmo dia, então eu coloquei o "date:Y-m-d" e com o modelo estabelecido resolveu o problema, e os testes passaram 

### ambiente de teste

o phpunit ta usando SQLlite em memoria
