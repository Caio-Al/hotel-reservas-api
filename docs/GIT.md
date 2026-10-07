# Padrão de Git do projeto

## Branches

- `main`: sempre estável; o que está nela roda e passa nos testes.
- Uma branch curta por tarefa, saindo da `main` e voltando por merge:
  - `feat/<assunto>`: funcionalidade nova (ex.: `feat/crud-quartos`)
  - `fix/<assunto>`: correção de bug
  - `docs/<assunto>`: só documentação
  - `test/<assunto>`, `chore/<assunto>`: testes e manutenção

## Commits (Conventional Commits)

Formato: `tipo: descrição curta no imperativo, em minúsculas`.

| Tipo | Quando usar |
|---|---|
| `feat` | funcionalidade nova |
| `fix` | correção de bug |
| `docs` | documentação |
| `test` | testes |
| `refactor` | muda o código sem mudar o comportamento |
| `chore` | configuração, dependências, ambiente |

Regras: um commit por mudança lógica, a aplicação funcionando em cada commit e nunca versionar segredos (`.env` fica fora; `.env.example` entra).

## Fluxo

```bash
git switch main && git pull
git switch -c feat/minha-tarefa
# ... trabalho ...
sail artisan test                 # só commitar com os testes passando
git add .
git commit -m "feat: descreve a mudanca"
git switch main
git merge --no-ff feat/minha-tarefa -m "merge: feat/minha-tarefa"
git push origin main
```

O `--no-ff` mantém um commit de merge, então o histórico mostra onde cada tarefa começou e terminou.

## Versionamento

Versões marcadas com tags semânticas (`v1.0.0`). A primeira entrega é a `v1.0.0`:

```bash
git tag -a v1.0.0 -m "Entrega do desafio: importador, API de quartos e reservas"
git push origin v1.0.0
```

## O que é versionado

Código, migrations, testes, `docs/` (incluindo o diagrama `.mwb`/`.png` e o contrato OpenAPI) e os XMLs em `database/xml/`. Não entram: `.env`, `vendor/`, `storage/logs/`.
