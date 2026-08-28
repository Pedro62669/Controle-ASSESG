# Controle-ASSESG

Controle do fluxo de caixa da ASSESG.

Sistema de controle de caixa construído com a **TALL stack**: Tailwind CSS 4, Alpine.js,
Laravel 13 e Livewire 4, com PHP 8.4 em tipagem estrita e formatação PSR-12 (Pint).

A paleta da interface foi extraída da logomarca da ASSESG:

| Papel | Cor | Uso |
| --- | --- | --- |
| `primary` | `#0B3A5D` | Azul-marinho da tipografia e da figura à esquerda |
| `secondary` | `#628B72` | Verde-sálvia das folhas — entradas |
| `accent` | `#C9B999` | Areia do coração e da figura à direita |
| `danger` | `#B4593F` | Terracota harmonizado — saídas |

## Instalação

```bash
composer install
npm install

cp .env.example .env          # se ainda não existir
php artisan key:generate
```

Crie o banco e ajuste as credenciais no `.env`:

```sql
CREATE DATABASE controle_assesg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=controle_assesg
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

Rode as migrations e crie o administrador principal:

```bash
php artisan migrate --seed
npm run build          # ou: npm run dev
php artisan serve
```

O seeder cria o acesso inicial **admin@assesg.org.br / assesg@2026** com
`is_main_admin = true`. **Troque essa senha no primeiro acesso.**

### Dados de demonstração (opcional)

```bash
php artisan db:seed --class=DemoDataSeeder
```

Popula a base com um ano de movimentações fictícias de uma associação
(repasses de convênio, doações, aluguel, cestas básicas) distribuídas entre
quatro responsáveis, com comprovantes PNG gerados em disco em parte dos
lançamentos — as duas metades da regra `required_without` ficam exercitadas.
Os lançamentos são criados com o responsável autenticado, então a tela de logs
também aparece populada. A semente é fixa: rodar de novo reproduz os mesmos
números.

Os usuários de demonstração (`tesouraria@`, `secretaria@`, `projetos@assesg.org.br`)
usam a senha `assesg@2026` e **não devem ir para produção**.

## Estrutura

| Camada | Onde |
| --- | --- |
| Enums | `app/Enums` — `TransactionType`, `LogAction`, `PeriodFilter` |
| Models | `app/Models` — `User`, `Transaction`, `SystemLog` |
| Observers | `app/Observers` — auditoria automática via `#[ObservedBy]` |
| Auditoria | `app/Support/ActivityLogger.php` — ponto único de gravação |
| Validação | `app/Validation` — regras compartilhadas entre Form Requests e Livewire |
| Form Requests | `app/Http/Requests` |
| Relatórios | `app/Services/CashFlowReportService.php` |
| Componentes Livewire | `app/Livewire` |
| Middleware | `app/Http/Middleware` — `main.admin`, `active` |

## Regras de negócio

- O valor da movimentação precisa ser **maior que zero** e a data é obrigatória
  (e não pode ser futura).
- Toda movimentação declara uma **fonte** (`App\Enums\TransactionSource`): a origem
  do dinheiro que entra (convênio público, doação, bazar…) ou o destino do que sai
  (aluguel, alimentação, pessoal…). Cada fonte pertence a um único tipo, e a regra
  `SourceMatchesTransactionType` impede que uma entrada receba fonte de saída.
  É por essa dimensão que os gráficos de pizza agrupam entradas e saídas.
- Entradas **e** saídas são classificadas em **pontual** ou **recorrente**; a recorrente
  exige intervalo (mensal a anual, ou meses escolhidos manualmente) e duração
  (indeterminada ou número de parcelas). Um auxílio mensal do poder público é tão
  recorrente quanto o aluguel da sede.
- O comprovante (PDF, JPG ou PNG, até 5 MB) é **opcional**. Se ele não for enviado,
  a **descrição passa a ser obrigatória** (`required_without:document_path`) e precisa
  ter **no mínimo 15 caracteres** justificando a origem ou o destino do dinheiro.
- Comprovantes ficam em `storage/app/private/transactions/AAAA/MM` e só são servidos
  pela rota autenticada `transactions.document` — nunca por URL pública.
- Movimentações e usuários usam *soft delete*: o histórico permanece auditável.

## Controle de acesso

| Área | Quem acessa |
| --- | --- |
| Dashboard e movimentações | Todo usuário autenticado e ativo |
| Cadastro de usuários (`/administracao/usuarios`) | Apenas `is_main_admin` |
| Logs do sistema (`/administracao/logs`) | Apenas `is_main_admin` |

O middleware `active` encerra a sessão de contas desativadas na requisição seguinte.

## Logs automáticos

`TransactionObserver` e `UserObserver` capturam `created`, `updated`, `deleted` e
`restored`, gravando em `system_logs` o usuário responsável, a descrição legível da
ação, os valores antes/depois, IP e user agent. Senhas e tokens nunca são gravados,
e alterações sem mudança real de dados não geram ruído na trilha.

## Dashboard

Filtro global de período (Hoje, Esta Semana, Este Mês, Este Ano ou intervalo
customizado) que atualiza reativamente os totalizadores e os quatro gráficos
(ApexCharts):

1. **Barras** — comparativo temporal de entradas x saídas, com granularidade
   automática (dia, semana ou mês conforme a amplitude do período);
2. **Pizza** — valor total retido (saldo em caixa x valor já utilizado);
3. **Pizza** — total de entradas do período por origem do recurso;
4. **Pizza** — total de saídas do período por destino do recurso.

### Projeção

Um bloco separado projeta os próximos 3, 6, 12 ou 24 meses a partir das movimentações
**recorrentes** já lançadas (`CashFlowProjectionService`), com barras de entradas e
saídas previstas e a linha do saldo acumulado, partindo do caixa de hoje.

A projeção agrupa lançamentos em **séries** (tipo + fonte + ciclo + descrição) e usa
apenas o mais recente de cada uma — um aluguel lançado todo mês é uma série só, não
oito projeções somadas. Em compras parceladas, desconta as parcelas já lançadas e
projeta somente as restantes. **Nada é gravado**: o saldo real continua refletindo
apenas o que foi efetivamente lançado.

## Testes e qualidade

```bash
php artisan test        # PHPUnit
./vendor/bin/pint       # PSR-12
```
