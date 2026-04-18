<?php

declare(strict_types=1);

namespace App\Enum\Editorial;

/**
 * Sensitive-topic taxonomy consumed by EscalationClassifier + EditorialEscalationLog
 * (Sprint 55 T55.8, ADR-020 D7).
 *
 * Seven universal escalation categories (categ_1..categ_7) + four
 * Moldova-specific sensitivity families (family_a..family_d).
 *
 * Enum values (the `->value`) are the short codes stored in DB. The
 * constant names (the `->name`) are the verbose identifiers used by the
 * 50-claim dataset and by public-facing APIs. Both mappings are stable.
 *
 *  Short (DB)     Verbose name                         Description
 *  ---------      ------------                         -----------
 *  categ_1        CATEGORY_1_NUCLEAR_WAR               Acts of war between nuclear states
 *  categ_2        CATEGORY_2_HEAD_OF_STATE_DEATH       Sudden death of G20/EU head of state or patriarch
 *  categ_3        CATEGORY_3_NBC_ATTACK                Nuclear / biological / chemical attack
 *  categ_4        CATEGORY_4_COUP                      Unconstitutional seizure of power
 *  categ_5        CATEGORY_5_MASS_CASUALTIES           >1000 victims in first 2h
 *  categ_6        CATEGORY_6_CRIMINAL_ACCUSATION       Personalised criminal accusations (defamation risk)
 *  categ_7        CATEGORY_7_PRE_CEC_ELECTORAL         Electoral results announced before CEC
 *  family_a       FAMILY_A_CHURCH                      Church / Patriarchate
 *  family_b       FAMILY_B_EU_NATO_RUSSIA              EU / NATO / Russia in MD context
 *  family_c       FAMILY_C_TRANSNISTRIA_GAGAUZIA       Transnistria / Găgăuzia sensitivity
 *  family_d       FAMILY_D_CEC_PARTY_LEADERS           CEC / major party leaders
 */
enum EscalationCategory: string
{
    case CATEGORY_1_NUCLEAR_WAR = 'categ_1';
    case CATEGORY_2_HEAD_OF_STATE_DEATH = 'categ_2';
    case CATEGORY_3_NBC_ATTACK = 'categ_3';
    case CATEGORY_4_COUP = 'categ_4';
    case CATEGORY_5_MASS_CASUALTIES = 'categ_5';
    case CATEGORY_6_CRIMINAL_ACCUSATION = 'categ_6';
    case CATEGORY_7_PRE_CEC_ELECTORAL = 'categ_7';
    case FAMILY_A_CHURCH = 'family_a';
    case FAMILY_B_EU_NATO_RUSSIA = 'family_b';
    case FAMILY_C_TRANSNISTRIA_GAGAUZIA = 'family_c';
    case FAMILY_D_CEC_PARTY_LEADERS = 'family_d';
}
