<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origem do dinheiro que entra e destino do que sai.
 *
 * Cada fonte pertence a um único tipo de movimentação — é o que permite
 * agrupar entradas e saídas por natureza nos relatórios.
 */
enum TransactionSource: string
{
    // Entradas
    case PublicAgreement = 'public_agreement';
    case MemberContribution = 'member_contribution';
    case IndividualDonation = 'individual_donation';
    case CompanyDonation = 'company_donation';
    case CharityEvent = 'charity_event';
    case SolidaritySale = 'solidarity_sale';
    case FinancialIncome = 'financial_income';
    case OtherIncome = 'other_income';

    // Saídas
    case Rent = 'rent';
    case Utilities = 'utilities';
    case FoodSupplies = 'food_supplies';
    case ConsumableSupplies = 'consumable_supplies';
    case Transport = 'transport';
    case Payroll = 'payroll';
    case OutsourcedServices = 'outsourced_services';
    case Maintenance = 'maintenance';
    case Equipment = 'equipment';
    case TaxesAndFees = 'taxes_and_fees';
    case OtherExpense = 'other_expense';

    public function label(): string
    {
        return match ($this) {
            self::PublicAgreement => 'Convênio público',
            self::MemberContribution => 'Contribuição de associados',
            self::IndividualDonation => 'Doação de pessoa física',
            self::CompanyDonation => 'Doação de empresa',
            self::CharityEvent => 'Evento beneficente',
            self::SolidaritySale => 'Bazar e venda solidária',
            self::FinancialIncome => 'Rendimento financeiro',
            self::OtherIncome => 'Outras entradas',

            self::Rent => 'Aluguel',
            self::Utilities => 'Água, luz e telefonia',
            self::FoodSupplies => 'Alimentação e cestas básicas',
            self::ConsumableSupplies => 'Material de consumo e limpeza',
            self::Transport => 'Transporte e combustível',
            self::Payroll => 'Pessoal e encargos',
            self::OutsourcedServices => 'Serviços de terceiros',
            self::Maintenance => 'Manutenção e reparos',
            self::Equipment => 'Equipamentos e mobiliário',
            self::TaxesAndFees => 'Tributos e taxas',
            self::OtherExpense => 'Outras saídas',
        };
    }

    public function type(): TransactionType
    {
        return match ($this) {
            self::PublicAgreement,
            self::MemberContribution,
            self::IndividualDonation,
            self::CompanyDonation,
            self::CharityEvent,
            self::SolidaritySale,
            self::FinancialIncome,
            self::OtherIncome => TransactionType::Income,

            default => TransactionType::Expense,
        };
    }

    /**
     * Fontes disponíveis para um tipo de movimentação.
     *
     * @return list<self>
     */
    public static function casesFor(TransactionType $type): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $case): bool => $case->type() === $type,
        ));
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(TransactionType $type): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::casesFor($type),
        );
    }
}
