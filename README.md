# Duel Legacy

Duel Legacy é um jogo de cartas web single-player inspirado na experiência
clássica/GX de Yu-Gi-Oh!. O projeto está na fase de fundação: o motor já modela
o estado inicial, RNG determinístico, preparação, compra e o fluxo estrutural
até a transição entre turnos; interface de Duelo, persistência e efeitos de
cartas ainda não foram implementados.

## Stack atual

- frontend: React, TypeScript e Vite;
- API: PHP 8.3+ e Laravel 13;
- regras: pacote PHP puro, independente de Laravel e HTTP;
- bot: pacote Composer PHP mínimo, sem IA;
- contratos: OpenAPI neutro em relação à linguagem;
- dependências: pnpm para o frontend e Composer para PHP;
- testes: PHPUnit para PHP;
- qualidade: ESLint, TypeScript, Prettier, PHPStan, Pint e validação de sintaxe PHP.

O servidor é a autoridade do Duelo. O frontend não decide se uma ação é legal,
e informações escondidas não devem ser enviadas ao adversário.

## Estrutura

```text
apps/
  web/                 React + TypeScript + Vite
  api/                 Laravel e adaptadores HTTP
packages/
  duel-engine/         domínio PHP puro e determinístico
  bot-engine/          fundação PHP para bots
  shared-contracts/    OpenAPI e contratos neutros
scripts/               orquestração e verificações do repositório
```

`apps/api` depende de `packages/duel-engine` por um repositório Composer local
do tipo `path`. O motor não depende de Laravel, HTTP, banco, frontend, Node.js
ou do bot. O `bot-engine` pode depender do motor, nunca o inverso.

## Requisitos locais

- PHP 8.3 ou superior compatível com as dependências travadas;
- Composer 2;
- Node.js compatível com Vite 8;
- pnpm 11.18.0.

As versões exatas resolvidas de Laravel, PHPUnit e demais pacotes PHP estão nos
arquivos `composer.lock` das aplicações e pacotes.

## Instalação

```bash
pnpm install
composer --working-dir=packages/duel-engine install
composer --working-dir=packages/bot-engine install
composer --working-dir=apps/api install
```

O projeto não requer banco de dados para iniciar a API, executar o health check
ou rodar os testes atuais.

## Desenvolvimento

Frontend:

```bash
pnpm dev:web
```

API Laravel:

```bash
pnpm dev:api
```

O endpoint disponível é:

```text
GET /health
```

Resposta:

```json
{ "status": "ok" }
```

O health check informa somente que a API está ativa. O carregamento do pacote
`duel-engine` pela aplicação Laravel é verificado separadamente pelo teste de
integração.

## Validação

Os comandos públicos da raiz validam as duas stacks:

```bash
pnpm build
pnpm test
pnpm lint
pnpm typecheck
pnpm format:check
```

- `build`: compila o frontend, valida estritamente os três manifests Composer,
  verifica o autoload PSR-4 otimizado e a sintaxe dos arquivos PHP;
- `test`: executa PHPUnit no motor e os testes Laravel;
- `lint`: executa ESLint, PHPStan no motor, bot e API, e Pint em modo de
  verificação;
- `typecheck`: executa TypeScript e PHPStan no motor, bot e API;
- `format:check`: executa Prettier e Pint sem alterar arquivos.

## Estado do motor

O `duel-engine` usa `strict_types=1`, PSR-4, enums backed, objetos readonly e
operações que retornam novos estados. Estão portados e cobertos por PHPUnit:

- perfil `GX_LEGACY` e suas validações;
- identificadores, cartas, jogadores, zonas e estado do Duelo;
- fases, etapas e transições estruturais;
- FNV-1a sobre unidades UTF-16 e `xorshift32` com operações de 32 bits;
- geração de números, Fisher–Yates, compra e preparação inicial;
- primeiro turno, Draw Phase, Standby, Main Phase 1 e próximo turno;
- Deck Out e consulta do descarte exigido pelo limite da mão.

A migração preservou fixtures geradas pela implementação TypeScript anterior.
Elas verificam paridade bit a bit do RNG para ASCII, whitespace Unicode do
ECMAScript, acentos, caracteres fora do BMP, fallback do hash FNV-1a zero,
continuação após serialização e o fluxo estrutural completo. Os comportamentos
públicos e as invariantes relevantes da versão TypeScript foram revalidados em
PHPUnit por testes e data providers; os 606 casos Vitest anteriores são apenas
o baseline histórico, não uma contagem de métodos portados individualmente.

A suíte atual contém 20 classes de teste do motor, com 368 testes e 6.439
assertions, e 2 testes Laravel com 4 assertions: 370 testes e 6.443 assertions
no total.

## Limitações atuais

- somente o perfil inicial `GX_LEGACY`;
- apenas `GET /health` na API;
- sem autenticação, banco, coleção, loja, campanha ou persistência de Duelos;
- sem interface completa de Duelo;
- sem efeitos de cartas, Correntes ou catálogo amplo;
- `bot-engine` ainda é somente uma fundação.

Os value objects públicos `DuelId`, `PlayerId` e `CardInstanceId` existem, mas
as assinaturas internas migradas ainda usam strings por compatibilidade; a
integração integral fica para uma refatoração futura. A garantia forte de
imutabilidade vale para os estados oficiais compostos por scalars, enums e
objetos readonly. Resultados genéricos de shuffle preservam as referências dos
itens recebidos e, portanto, não prometem imutabilidade profunda de objetos
mutáveis arbitrários.

Imagens oficiais de cartas não devem ser adicionadas ao repositório.
