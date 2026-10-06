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

## migrations e models (base do banco)

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


## estrutura para as regras do sistema, com usuários, cupons e preço

o desafio pedia desconto, acréscimo, usuários com permissões e gestão de pagamentos. isso muda o banco, então fiz a estrutura antes do importador e do diagrama, pra o modelo ficar completo de uma vez e n precisar mexer nas tabelas depois

### usuários e permissões

usei a tabela users que o laravel ja tras e coloquei 2 colunas a mais, role e hotel_id

são 3 ranks, entre admin, gerente e recepcionista

adotei o POLP em todos, então eles tem o menor privilegio possivel para sua função.

o admin acessa todos os hotéis, seriam contas de suporte
o gerente cadastra quartos e opera reservas do proprio hotel
o recepcionista so opera reservas e pagamentos do proprio hotel 

um usuario se n for declarando antes, por padrão vem como recepcionista, 

o hotel_id liga o gerente e o recepcionista ao hotel deles e para admin fica vazio 

role e hotel_id ficam fora do fillable, pq assim n da pra promover por mass assigment, so o codigo do servidor define o papel

os usuarios são so da equipe do hotel. O hóspede não é usuário e não faz login ele é um dado da reserva a tabela guests e o unico contato que o XML traz é o telefone, então n criei e-mail. A reserva também não guarda qual funcionairio a criou, ela se liga ao hotel e ao quarto

fiz também as regras que ficaram numa trait HasHotelRole com canManageHotel e canOperateHotel, mas atual so a estrutura existe, as rotas pras regas vou por depois

## cupons

criei a tabela coupons com codigo unico, tipo, valor validade, limite de uso e contdor de uso, pelo menos nesse primeiro momento, a validadação vou fazer depois quando fazer resevas e pagamentos, 

## preço na reserva

coloquei subtotal, discount, service_fee e coupon_id na tabela reserves, e o total é (subtotal -desconto) + taxa de serviço

e gravei os valores na propria reserva, porque se um cupom ou taxa mudarem no futuro as reservas antigas continuam com o valor que foi cobrado.

## como testei

rodei o migrate e depois rodei os testes, que tem od novos e os antigos, fazendo o teste em cascata, assim eu sei se algo quebrou por modificações novas

