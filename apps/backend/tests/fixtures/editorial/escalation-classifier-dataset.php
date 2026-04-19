<?php

declare(strict_types=1);

/**
 * EscalationClassifier empirical-validation dataset (Sprint 55 T55.15, ADR-020 D7).
 *
 * 50 role-based claims spanning 7 universal escalation categories + 4 Moldovan
 * sensitivity families. Each entry pairs a plausible signal title + summary
 * with the expected classification outcome. The dataset backs
 * {@see App\Tests\Unit\Service\Editorial\Escalation\EscalationClassifierEmpiricalTest}
 * (un-skipped in T55.8) — the acceptance bar is false-negative rate < 5%.
 *
 * Authorship rules honoured throughout:
 *  - Romanian diacritics are comma-below (ș U+0219, ț U+021B).
 *  - NO real person names. Roles only ("un deputat în funcție", "președintele
 *    unei formațiuni parlamentare"). Archetypes protect the dataset from
 *    defamation risk while still stressing classifier nuance.
 *  - Positives MUST be unambiguous escalations (role-based trigger per D7).
 *  - Negatives MUST be subtle edges the classifier might confuse: routine
 *    coverage inside the same topical family, or later-revised estimates.
 *
 * Radu reviews this in T55.18 against the companion Markdown file in
 * `40_Agent_Workspace/Review/sprint-55-escalation-dataset.md`.
 *
 * Distribution (50 total):
 *   CATEGORY_1_NUCLEAR_WAR           3 positive + 1 negative =  4
 *   CATEGORY_2_HEAD_OF_STATE_DEATH   3 positive + 1 negative =  4
 *   CATEGORY_3_NBC_ATTACK            3 positive + 0 negative =  3
 *   CATEGORY_4_COUP                  3 positive + 1 negative =  4
 *   CATEGORY_5_MASS_CASUALTIES       3 positive + 1 negative =  4
 *   CATEGORY_6_CRIMINAL_ACCUSATION   3 positive + 2 negative =  5
 *   CATEGORY_7_PRE_CEC_ELECTORAL     3 positive + 1 negative =  4
 *   FAMILY_A_CHURCH                  3 positive + 2 negative =  5
 *   FAMILY_B_EU_NATO_RUSSIA          3 positive + 2 negative =  5
 *   FAMILY_C_TRANSNISTRIA_GAGAUZIA   4 positive + 2 negative =  6
 *   FAMILY_D_CEC_PARTY_LEADERS       4 positive + 2 negative =  6
 *                                                    total   = 50
 *
 * @return list<array{
 *     id: string,
 *     title: string,
 *     summary: string,
 *     expected_category: string,
 *     expected_is_escalation: bool,
 *     notes: string,
 * }>
 */
return [
    // ====================================================================
    // CATEGORY 1 — Acts of war between nuclear states
    // ====================================================================
    [
        'id' => 'cat1_positive_1',
        'title' => 'Presa rusă anunță lansarea unei lovituri cu rachetă balistică cu încărcătură nucleară asupra capitalei unui stat aliat NATO',
        'summary' => 'Surse neconfirmate din agenții ruse raportează că Moscova ar fi autorizat o lovitură nucleară tactică asupra unui oraș european. Nu există confirmare din partea statelor vizate sau a aliaților NATO.',
        'expected_category' => 'CATEGORY_1_NUCLEAR_WAR',
        'expected_is_escalation' => true,
        'notes' => 'Primul uz nuclear în Europa — trigger absolut, necesită verificare editorială înainte de orice publicare.',
    ],
    [
        'id' => 'cat1_positive_2',
        'title' => 'Canale militare pakistaneze confirmă schimb de focuri nucleare tactice cu India în zona de contact himalayană',
        'summary' => 'Agenția de presă de stat a unui stat nuclear anunță detonarea unui dispozitiv tactic într-un sector controlat de un alt stat nuclear. Nicio confirmare din partea celui de-al doilea stat.',
        'expected_category' => 'CATEGORY_1_NUCLEAR_WAR',
        'expected_is_escalation' => true,
        'notes' => 'Escaladare între două puteri nucleare — același nivel de gravitate, necesită dublă atribuire.',
    ],
    [
        'id' => 'cat1_positive_3',
        'title' => 'Forțe armate ale unui stat nuclear confirmă utilizarea unui dispozitiv cu randament scăzut pe teritoriul unui adversar regional',
        'summary' => 'Comunicat oficial al ministerului apărării al unui stat cu arsenal nuclear declarat descrie detonarea unui dispozitiv cu randament sub-kiloton. Reacția internațională nu a fost încă formulată.',
        'expected_category' => 'CATEGORY_1_NUCLEAR_WAR',
        'expected_is_escalation' => true,
        'notes' => 'Prag nuclear depășit — escaladare prin propria admitere a statului atacator.',
    ],
    [
        'id' => 'cat1_negative_1',
        'title' => 'Schimb de focuri de artilerie la linia de control dintre două puteri nucleare regionale, fără victime civile',
        'summary' => 'Armata unui stat nuclear raportează tiruri de artilerie în sectorul disputat al frontierei cu un vecin nuclear. Schimb de focuri rutinier conform cadrului de contestație stabilit de peste două decenii.',
        'expected_category' => 'CATEGORY_1_NUCLEAR_WAR',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — artilerie convențională la LoC, nu are dimensiune nucleară. Ușor de confundat prin prezența a doi actori nucleari.',
    ],

    // ====================================================================
    // CATEGORY 2 — Sudden death of head of state (G20 / EU / Orthodox)
    // ====================================================================
    [
        'id' => 'cat2_positive_1',
        'title' => 'Cancelaria federală a unei mari puteri europene confirmă decesul șefului statului în exercitarea funcției',
        'summary' => 'Comunicat oficial al cancelariei unei puteri UE anunță decesul președintelui în timpul unei vizite de lucru. Cauza rămâne neclară; procedurile de succesiune au fost activate.',
        'expected_category' => 'CATEGORY_2_HEAD_OF_STATE_DEATH',
        'expected_is_escalation' => true,
        'notes' => 'Moarte subită a unui șef de stat UE — impact direct asupra stabilității și piețelor.',
    ],
    [
        'id' => 'cat2_positive_2',
        'title' => 'Patriarhia ortodoxă dintr-o jurisdicție aliată confirmă decesul patriarhului la scurt timp după o ședință a sinodului',
        'summary' => 'Biroul de presă al unui patriarhat ortodox principal anunță decesul patriarhului. Ceremoniile de succesiune canonică sunt activate; statele cu comuniune liturgică sunt în doliu.',
        'expected_category' => 'CATEGORY_2_HEAD_OF_STATE_DEATH',
        'expected_is_escalation' => true,
        'notes' => 'Lider religios de rang patriarhal cu impact geopolitic — încadrat în D7 alături de șefi de stat.',
    ],
    [
        'id' => 'cat2_positive_3',
        'title' => 'Administrația prezidențială a unei puteri nucleare anunță decesul șefului statului și intrarea în vigoare a protocolului de succesiune',
        'summary' => 'Comunicat al aparatului prezidențial confirmă decesul. Guvernul a preluat atribuțiile interimare; piețele și statele vecine sunt în așteptarea clarificării succesiunii.',
        'expected_category' => 'CATEGORY_2_HEAD_OF_STATE_DEATH',
        'expected_is_escalation' => true,
        'notes' => 'Moarte a liderului unei puteri nucleare — impact sistemic și securitate strategică.',
    ],
    [
        'id' => 'cat2_negative_1',
        'title' => 'Prim-ministrul unui stat european anunță retragerea din funcție pentru motive medicale',
        'summary' => 'Prim-ministrul în exercițiu declară, într-un comunicat video, că se va retrage la finalul mandatului actual din motive de sănătate. Tratamentul este descris ca „de rutină”.',
        'expected_category' => 'CATEGORY_2_HEAD_OF_STATE_DEATH',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — retragere planificată, nu deces. Ușor de confundat cu pozitivele prin tema sănătate-funcție.',
    ],

    // ====================================================================
    // CATEGORY 3 — NBC attacks (nuclear, biological, chemical)
    // ====================================================================
    [
        'id' => 'cat3_positive_1',
        'title' => 'Echipe medicale raportează simptome compatibile cu un agent neurotoxic în rândul civililor dintr-un oraș portuar ucrainean',
        'summary' => 'Spitale locale descriu simptome de intoxicație masivă cu substanță organofosforică. OMS și OPCW nu au emis încă o constatare; autoritățile solicită ancheta internațională.',
        'expected_category' => 'CATEGORY_3_NBC_ATTACK',
        'expected_is_escalation' => true,
        'notes' => 'Atac chimic potențial — escaladare chiar și fără confirmare OPCW, dat fiind pragul NBC.',
    ],
    [
        'id' => 'cat3_positive_2',
        'title' => 'Servicii de urgență din Kyiv izolează stația de metrou după raportarea unei substanțe cu aerosol suspecte',
        'summary' => 'Serviciile de intervenție au izolat o stație de metrou după detectarea unui agent aerosol suspectat a fi biologic. Victimele sunt în carantină; laboratoarele analizează probele.',
        'expected_category' => 'CATEGORY_3_NBC_ATTACK',
        'expected_is_escalation' => true,
        'notes' => 'Atac biologic posibil în spațiu public — escaladare NBC chiar și la stadiul de suspiciune justificată.',
    ],
    [
        'id' => 'cat3_positive_3',
        'title' => 'Forțele armate ale unui stat aliat raportează detectarea de material radioactiv într-un proiectil de artilerie recuperat în Donbas',
        'summary' => 'Inspecția unui proiectil de artilerie recuperat pe frontul de est indică prezența unui izotop radioactiv necompatibil cu muniția convențională. Probele sunt trimise la laboratoare specializate.',
        'expected_category' => 'CATEGORY_3_NBC_ATTACK',
        'expected_is_escalation' => true,
        'notes' => 'Armament radiologic („dirty shell”) — prag NBC, chiar și în absența detonației.',
    ],

    // ====================================================================
    // CATEGORY 4 — Coups (unconstitutional seizure of power)
    // ====================================================================
    [
        'id' => 'cat4_positive_1',
        'title' => 'Forțe armate ale unui stat dintr-o regiune instabilă preiau controlul palatului prezidențial și suspendă constituția',
        'summary' => 'Un comandant militar anunță, printr-un mesaj transmis de televiziunea de stat, suspendarea constituției și formarea unui consiliu militar provizoriu. Președintele ales este reținut.',
        'expected_category' => 'CATEGORY_4_COUP',
        'expected_is_escalation' => true,
        'notes' => 'Lovitură militară clasică — escaladare imediată, necesită atribuire oficială.',
    ],
    [
        'id' => 'cat4_positive_2',
        'title' => 'Garda prezidențială dizolvă parlamentul unui stat african și instalează un guvern provizoriu',
        'summary' => 'Comandantul gărzii prezidențiale anunță dizolvarea parlamentului în urma unui „deficit constituțional” și formarea unui executiv interimar. Frontierele sunt închise.',
        'expected_category' => 'CATEGORY_4_COUP',
        'expected_is_escalation' => true,
        'notes' => 'Dizolvare unilaterală a parlamentului + închidere frontiere = indicatori clari de coup.',
    ],
    [
        'id' => 'cat4_positive_3',
        'title' => 'Curtea supremă dintr-un stat central-asiatic este ocupată de unități militare; judecătorii sunt evacuați forțat',
        'summary' => 'Unități militare au ocupat sediul curții supreme și au evacuat personalul. Un nou președinte al instanței este numit pe loc printr-un decret al consiliului militar.',
        'expected_category' => 'CATEGORY_4_COUP',
        'expected_is_escalation' => true,
        'notes' => 'Capturarea instanțelor supreme + înlocuire prin decret militar = coup judicial.',
    ],
    [
        'id' => 'cat4_negative_1',
        'title' => 'Președintele unei țări din Europa Centrală anunță remanierea integrală a cabinetului ministerial',
        'summary' => 'Șeful statului a cerut prim-ministrului remanierea totală a guvernului. Procedura se desfășoară în cadrul constituțional, cu validarea parlamentului.',
        'expected_category' => 'CATEGORY_4_COUP',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — remaniere constituțională, nu coup. Confuzia vine din „schimbare radicală de guvern”.',
    ],

    // ====================================================================
    // CATEGORY 5 — Mass casualties (>1000 killed in first 2h)
    // ====================================================================
    [
        'id' => 'cat5_positive_1',
        'title' => 'Cutremur de magnitudine 7.8 produce prăbușiri masive într-un oraș din Asia de Sud-Est; primele estimări indică peste 2.000 de decese în prima oră',
        'summary' => 'USGS confirmă un cutremur de magnitudine 7.8 cu epicentru la 10 km. Agențiile locale estimează peste 2.000 de decese pe baza raportării spitalelor în prima oră.',
        'expected_category' => 'CATEGORY_5_MASS_CASUALTIES',
        'expected_is_escalation' => true,
        'notes' => 'Dezastru natural cu peste 1.000 de victime în prima oră — trigger automat de verdict cu atribuire.',
    ],
    [
        'id' => 'cat5_positive_2',
        'title' => 'Explozie puternică într-o gară feroviară din Asia Centrală; autoritățile raportează peste 1.200 de victime în primele 90 de minute',
        'summary' => 'Raport inițial al ministerului sănătății indică peste 1.200 de victime în primele 90 de minute după o explozie într-o gară aglomerată. Cauza este încă neclară — atac terorist, accident sau sabotaj.',
        'expected_category' => 'CATEGORY_5_MASS_CASUALTIES',
        'expected_is_escalation' => true,
        'notes' => 'Eveniment în curs cu număr mare de victime în fereastra 2h — escaladare chiar și fără cauză stabilită.',
    ],
    [
        'id' => 'cat5_positive_3',
        'title' => 'Cedare masivă a unui baraj hidrotehnic; primele estimări indică peste 1.500 de decese în prima oră în valea inundată',
        'summary' => 'Un baraj mare a cedat în urma unor precipitații istorice. Autoritățile locale raportează peste 1.500 de decese în prima oră; mii de oameni sunt dați dispăruți în valea inundată.',
        'expected_category' => 'CATEGORY_5_MASS_CASUALTIES',
        'expected_is_escalation' => true,
        'notes' => 'Dezastru infrastructural cu impact imediat — trigger mass_casualties.',
    ],
    [
        'id' => 'cat5_negative_1',
        'title' => 'Bilanțul oficial al unei calamități naturale este revizuit la 350 de decese confirmate după șase ore, față de estimările inițiale de peste 2.000',
        'summary' => 'Autoritățile actualizează bilanțul după șase ore de la eveniment: numărul confirmat scade de la estimările inițiale de peste 2.000 la 350 de victime. Echipele de salvare continuă să caute supraviețuitori.',
        'expected_category' => 'CATEGORY_5_MASS_CASUALTIES',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — revizuire în scădere sub pragul 1.000, și dincolo de fereastra 2h. Subtilitatea: trigger-ul e pentru estimarea inițială în prima oră, nu pentru revizuire post-fapt.',
    ],

    // ====================================================================
    // CATEGORY 6 — Personalized criminal accusations (defamation risk)
    // ====================================================================
    [
        'id' => 'cat6_positive_1',
        'title' => 'O publicație investigativă susține că un deputat în funcție ar fi implicat într-o schemă de trafic de persoane, fără dosar penal deschis',
        'summary' => 'Un material investigativ afirmă implicarea unui deputat în trafic de persoane, pe baza unor surse neidentificate. Nu există un dosar penal deschis, nici sesizare oficială din partea procurorilor.',
        'expected_category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
        'expected_is_escalation' => true,
        'notes' => 'Acuzație penală personalizată fără suport procedural — risc major de defăimare, trigger Cat 6.',
    ],
    [
        'id' => 'cat6_positive_2',
        'title' => 'O martoră anonimă acuză un fost ministru de abuz sexual într-o declarație publicată pe un canal Telegram',
        'summary' => 'Un canal Telegram publică declarația unei martore anonime care acuză un fost ministru de abuz sexual. Nu există plângere penală, nici proces formal.',
        'expected_category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
        'expected_is_escalation' => true,
        'notes' => 'Acuzație de agresiune sexuală, sursă anonimă, fără plângere — pragul maxim Cat 6.',
    ],
    [
        'id' => 'cat6_positive_3',
        'title' => 'Un denunțător din interiorul unei companii de stat susține că un primar ar fi primit mită pentru atribuirea unui contract public',
        'summary' => 'Un angajat al unei companii de stat afirmă într-un material media că un primar ar fi primit mită în contextul atribuirii unui contract public. Ancheta internă nu a fost deschisă, sesizarea la procuratură nu a fost formulată.',
        'expected_category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
        'expected_is_escalation' => true,
        'notes' => 'Acuzație de corupție personalizată fără dosar — trigger Cat 6.',
    ],
    [
        'id' => 'cat6_negative_1',
        'title' => 'Instanța supremă confirmă condamnarea definitivă a unui fost demnitar pentru fapte de corupție',
        'summary' => 'Hotărârea rămâne definitivă după respingerea recursului. Sentința inițială a instanței de fond este menținută, iar condamnatul începe executarea pedepsei.',
        'expected_category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — condamnare definitivă, nu acuzație. Fapta e stabilită juridic; clasa Cat 6 vizează acuzații nefundamentate, nu ceva dovedit.',
    ],
    [
        'id' => 'cat6_negative_2',
        'title' => 'Un fost director al unei agenții publice pledează vinovat în instanță pentru evaziune fiscală',
        'summary' => 'Fostul director a recunoscut faptele în fața instanței, încheind un acord de recunoaștere cu procurorii. Sentința urmează a fi pronunțată în următoarele săptămâni.',
        'expected_category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — recunoaștere în instanță, nu acuzație. Confuzia potențială e cu termenii "evaziune fiscală" și "director".',
    ],

    // ====================================================================
    // CATEGORY 7 — Pre-CEC electoral results (announcing before CEC)
    // ====================================================================
    [
        'id' => 'cat7_positive_1',
        'title' => 'Un canal de televiziune anunță „rezultatele finale” ale alegerilor parlamentare, cu ore înaintea anunțului Comisiei Electorale Centrale',
        'summary' => 'Un post TV publică cifre prezentate drept „rezultate finale oficiale” înainte ca CEC să emită raportul intermediar. Sondaj exit poll amestecat cu procesarea preliminară a secțiilor.',
        'expected_category' => 'CATEGORY_7_PRE_CEC_ELECTORAL',
        'expected_is_escalation' => true,
        'notes' => 'Prezentare de rezultate ca „finale” pre-CEC — trigger absolut în ziua alegerilor.',
    ],
    [
        'id' => 'cat7_positive_2',
        'title' => 'Un canal Telegram cu public larg publică „învingătorul” la primăria unui oraș-cheie cu patru ore înaintea raportului CEC',
        'summary' => 'Canalul publică nume, procent și mesaj de felicitare pentru un candidat la primărie, înainte ca CEC să emită vreun raport oficial preliminar.',
        'expected_category' => 'CATEGORY_7_PRE_CEC_ELECTORAL',
        'expected_is_escalation' => true,
        'notes' => 'Proclamare nepermisă pe canale paralele — amplifică riscul de dezinformare electorală.',
    ],
    [
        'id' => 'cat7_positive_3',
        'title' => 'O sursă anonimă „din interiorul CEC” afirmă că un candidat prezidențial ar fi câștigat primul tur',
        'summary' => 'O sursă anonimă citată într-un articol susține rezultatul primului tur al prezidențialelor. CEC nu a emis încă raportul preliminar; procesarea procese-verbale este în curs.',
        'expected_category' => 'CATEGORY_7_PRE_CEC_ELECTORAL',
        'expected_is_escalation' => true,
        'notes' => 'Sursă anonimă „din interior” + atribuire fără confirmare = escaladare pre-CEC.',
    ],
    [
        'id' => 'cat7_negative_1',
        'title' => 'Exit poll: rezultatele preliminare ale unui sondaj la ieșirea din secții, marcat explicit ca „sondaj, nu rezultate oficiale”',
        'summary' => 'Casa de sondaje publică cifrele sondajului la ieșirea din secții însoțite de un avertisment evident că datele nu reprezintă rezultate oficiale CEC și marja de eroare este indicată.',
        'expected_category' => 'CATEGORY_7_PRE_CEC_ELECTORAL',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — exit poll etichetat corect ca sondaj. Subtilitatea e că apare la aceeași oră cu trigger-ele pre-CEC, dar conține avertismentul care-l scoate din zona de risc.',
    ],

    // ====================================================================
    // FAMILY A — Church / Patriarchate (MD context: Metropolia / MP)
    // ====================================================================
    [
        'id' => 'family_a_positive_1',
        'title' => 'Mitropolitul dintr-o jurisdicție ortodoxă moldovenească anunță ruperea comuniunii liturgice cu Patriarhia Moscovei',
        'summary' => 'În urma unei ședințe a sinodului, mitropolitul anunță că jurisdicția rupe comuniunea liturgică cu Patriarhia Moscovei, invocând poziția acesteia asupra războiului din Ucraina.',
        'expected_category' => 'FAMILY_A_CHURCH',
        'expected_is_escalation' => true,
        'notes' => 'Sciziune canonică majoră cu impact geopolitic regional — trigger Family A.',
    ],
    [
        'id' => 'family_a_positive_2',
        'title' => 'Patriarhia Moscovei anunță suspendarea în bloc a clericilor moldoveni care au semnat o petiție pentru autocefalie',
        'summary' => 'Sinodul Patriarhiei Moscovei emite un decret de suspendare pentru zeci de clerici moldoveni semnatari ai unei petiții către Patriarhia Română. Reacția locală așteaptă clarificare.',
        'expected_category' => 'FAMILY_A_CHURCH',
        'expected_is_escalation' => true,
        'notes' => 'Decizie sinodală de sancționare colectivă — impact asupra comuniunilor locale, trigger Family A.',
    ],
    [
        'id' => 'family_a_positive_3',
        'title' => 'Autoritățile laice sechestrează proprietăți ale unei jurisdicții bisericești în urma unei hotărâri a sinodului unei ierarhii rivale',
        'summary' => 'Executorii judecătorești au pus sechestru pe o mănăstire istorică, în executarea unei hotărâri asupra proprietății cerute de o ierarhie rivală. Protestul credincioșilor continuă la fața locului.',
        'expected_category' => 'FAMILY_A_CHURCH',
        'expected_is_escalation' => true,
        'notes' => 'Executare silită asupra proprietăților bisericești + protest civic — sensibilitate comunitară ridicată.',
    ],
    [
        'id' => 'family_a_negative_1',
        'title' => 'Slujba de Bobotează la catedrala mitropolitană adună mii de credincioși; autoritățile laice organizează trafic suplimentar',
        'summary' => 'Slujba anuală de Bobotează s-a desfășurat conform tradiției. Autoritățile municipale au blocat traficul în zona catedralei pentru a facilita afluxul credincioșilor.',
        'expected_category' => 'FAMILY_A_CHURCH',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — acoperire rutinieră a unui moment liturgic major. Confuzia apare din „mitropolit” + „autoritate laică” în același titlu.',
    ],
    [
        'id' => 'family_a_negative_2',
        'title' => 'Un nou diacon este hirotonit în cadrul unei slujbe festive la catedrala din centrul capitalei',
        'summary' => 'Ceremonia de hirotonire a unui nou diacon s-a desfășurat în prezența familiei și a enoriașilor. Nu au fost incidente; slujba este o practică canonică rutinieră.',
        'expected_category' => 'FAMILY_A_CHURCH',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — eveniment canonic rutinier, fără dimensiune politică.',
    ],

    // ====================================================================
    // FAMILY B — EU / NATO / Russia in the MD context
    // ====================================================================
    [
        'id' => 'family_b_positive_1',
        'title' => 'Ministerul de Externe al Federației Ruse declară Republica Moldova „stat ostil” în contextul apropierii de NATO',
        'summary' => 'Comunicat oficial al MFA rus califică Moldova drept „stat ostil”, invocând apropierea de NATO și alinierea la sancțiunile UE. Chișinăul solicită convocarea ambasadorului rus.',
        'expected_category' => 'FAMILY_B_EU_NATO_RUSSIA',
        'expected_is_escalation' => true,
        'notes' => 'Declarație formală ostilă de la o putere regională — impact major, trigger Family B.',
    ],
    [
        'id' => 'family_b_positive_2',
        'title' => 'Un comunicat al summitului NATO include pentru prima dată o clauză explicită de asistență pentru apărarea Republicii Moldova',
        'summary' => 'Textul comunicatului final al summitului NATO conține o referință explicită la Moldova, angajând aliații la cooperare în domeniul apărării. Reacția Moscovei este așteptată.',
        'expected_category' => 'FAMILY_B_EU_NATO_RUSSIA',
        'expected_is_escalation' => true,
        'notes' => 'Schimbare de poziționare NATO pe MD — prag înalt, trigger Family B.',
    ],
    [
        'id' => 'family_b_positive_3',
        'title' => 'Consiliul UE adoptă un pachet de sancțiuni care vizează demnitari moldoveni acuzați de subminarea statului',
        'summary' => 'Pachetul de sancțiuni UE include nume de demnitari moldoveni acuzați de coordonare cu actori externi. Lista urmează a fi publicată oficial în Monitorul UE.',
        'expected_category' => 'FAMILY_B_EU_NATO_RUSSIA',
        'expected_is_escalation' => true,
        'notes' => 'Sancțiuni UE cu impact nominal asupra demnitarilor moldoveni — trigger Family B, risc defăimare la nume neconfirmate.',
    ],
    [
        'id' => 'family_b_negative_1',
        'title' => 'Ambasada României găzduiește un eveniment cultural dedicat zilei naționale',
        'summary' => 'Ambasada găzduiește recepția anuală dedicată zilei naționale, cu participarea comunității românești din capitală. Evenimentul are caracter protocolar.',
        'expected_category' => 'FAMILY_B_EU_NATO_RUSSIA',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — eveniment diplomatic rutinier, fără dimensiune geopolitică activă.',
    ],
    [
        'id' => 'family_b_negative_2',
        'title' => 'Delegația UE la Chișinău publică raportul trimestrial despre implementarea reformelor economice',
        'summary' => 'Raportul UE acoperă progresul tehnic pe capitole de aderare: sector bancar, digitalizare, reformă justiție. Ton general constructiv, observații tehnice standard.',
        'expected_category' => 'FAMILY_B_EU_NATO_RUSSIA',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — raport tehnic rutinier, fără escaladare politică. Confuzia vine din prezența UE + reformă.',
    ],

    // ====================================================================
    // FAMILY C — Transnistria / Găgăuzia sensitivity
    // ====================================================================
    [
        'id' => 'family_c_positive_1',
        'title' => 'Autoritățile de la Tiraspol anunță organizarea unui referendum unilateral privind „aderarea” la Federația Rusă',
        'summary' => 'Un comunicat al „Sovietului Suprem” de la Tiraspol anunță data unui referendum unilateral privind aderarea la Federația Rusă. Chișinăul și OSCE califică demersul drept nul și provocator.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => true,
        'notes' => 'Referendum unilateral pro-anexare — trigger absolut Family C.',
    ],
    [
        'id' => 'family_c_positive_2',
        'title' => 'Adunarea Populară a Găgăuziei adoptă o lege de extindere a atribuțiilor autonomiei, în afara cadrului constituțional moldovenesc',
        'summary' => 'Un vot cu majoritate calificată al Adunării Populare adoptă o lege care extinde atribuțiile autonomiei găgăuze dincolo de cadrul convenit prin legea specială. Guvernul central anunță sesizarea Curții Constituționale.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => true,
        'notes' => 'Confruntare constituțională regiune-centru în zona de sensibilitate etnică — trigger Family C.',
    ],
    [
        'id' => 'family_c_positive_3',
        'title' => 'Forțele de securitate din Transnistria blochează accesul observatorilor OSCE la un punct de control în zona de securitate',
        'summary' => 'Misiunea OSCE în Moldova raportează blocarea accesului observatorilor săi la un punct de control în Zona de Securitate, pentru a doua zi consecutiv. Comisia Unificată de Control nu a fost convocată.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => true,
        'notes' => 'Obstrucționare OSCE în zona de securitate — indicator escaladare, trigger Family C.',
    ],
    [
        'id' => 'family_c_positive_4',
        'title' => 'Comitetul Executiv al Găgăuziei anunță formarea unei „comisii electorale paralele” pentru alegerile parlamentare',
        'summary' => 'Comitetul executiv găgăuz anunță formarea unei comisii electorale regionale separate pentru alegerile parlamentare naționale. Autoritățile centrale și CEC califică demersul drept neconstituțional.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => true,
        'notes' => 'Structură electorală paralelă — amenințare directă la integritatea scrutinului.',
    ],
    [
        'id' => 'family_c_negative_1',
        'title' => 'Festivalul cultural găgăuz din Comrat celebrează 29 de ani de la autonomie cu spectacole folclorice',
        'summary' => 'Festivalul anual s-a desfășurat conform programului. Spectacole folclorice, târg de produse locale și lansări de carte. Oficialii centrali au participat alături de cei regionali.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — eveniment cultural rutinier. Subtilitate: celebrarea autonomiei e un subiect sensibil, dar în cadrul său legitim.',
    ],
    [
        'id' => 'family_c_negative_2',
        'title' => 'Echipa de fotbal din Transnistria învinge un adversar din Chișinău în finala Cupei Moldovei',
        'summary' => 'Meciul s-a desfășurat fără incidente. Echipa transnistreană învinge după lovituri de departajare. Trofeul este înmânat de președintele federației de fotbal.',
        'expected_category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — eveniment sportiv rutinier. Confuzia vine din prezența unei echipe transnistrene într-un context național.',
    ],

    // ====================================================================
    // FAMILY D — CEC / major party leaders
    // ====================================================================
    [
        'id' => 'family_d_positive_1',
        'title' => 'Liderul unei formațiuni parlamentare afirmă, într-o ședință internă înregistrată fără consimțământ, că scrutinul va fi „oricum falsificat”',
        'summary' => 'O înregistrare audio scursă indică liderul unei formațiuni parlamentare afirmând în fața echipei proprii că „oricum va fi falsificat scrutinul”. Formațiunea contestă autenticitatea; analiza tehnică e în curs.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => true,
        'notes' => 'Acuzație de fraudă electorală preventivă + înregistrare autentificată parțial — trigger Family D.',
    ],
    [
        'id' => 'family_d_positive_2',
        'title' => 'Un membru al CEC este acuzat de manipulare a urnei de vot printr-o sesizare a unui denunțător din interiorul instituției',
        'summary' => 'O sesizare depusă la conducerea CEC acuză un membru al comisiei de manipulare a unei urne de vot în cadrul unui scrutin local. Ancheta internă este activată, dar nu a fost deschis dosar penal.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => true,
        'notes' => 'Acuzație de manipulare electorală personalizată, fără dosar penal — trigger Family D + tangență Cat 6.',
    ],
    [
        'id' => 'family_d_positive_3',
        'title' => 'Un fost prim-ministru își anunță retragerea din formațiunea politică după un scandal intern privind atribuirea fondurilor de campanie',
        'summary' => 'Fostul prim-ministru anunță părăsirea formațiunii. Comunicatul face referire la un scandal intern privind fondurile de campanie, fără detalii procedurale.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => true,
        'notes' => 'Demisie cu fundal de scandal financiar — impact politic, necesită verificare editorială.',
    ],
    [
        'id' => 'family_d_positive_4',
        'title' => 'Liderul unui bloc parlamentar declară boicotul integral al ciclului electoral, inclusiv al alegerilor prezidențiale',
        'summary' => 'Liderul blocului parlamentar anunță într-o conferință de presă boicotul integral al ciclului electoral. Partidele din bloc analizează poziția individuală; societatea civilă avertizează asupra efectelor.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => true,
        'notes' => 'Boicot electoral integral — eveniment cu impact sistemic asupra legitimității scrutinului.',
    ],
    [
        'id' => 'family_d_negative_1',
        'title' => 'Congresul unei formațiuni parlamentare alege un nou vicepreședinte și adoptă programul pe noul mandat',
        'summary' => 'Congresul s-a desfășurat conform statutului. Noul vicepreședinte a fost ales cu majoritate, programul a fost adoptat fără amendamente majore.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — procedură statutară rutinieră. Confuzia vine din schimbarea de leadership.',
    ],
    [
        'id' => 'family_d_negative_2',
        'title' => 'Un deputat susține o conferință de presă despre agenda legislativă a comisiei pe care o conduce',
        'summary' => 'Deputatul prezintă proiectele de lege pe care comisia urmează să le dezbată, inclusiv termenele de avizare. Nu au fost făcute declarații politice majore.',
        'expected_category' => 'FAMILY_D_CEC_PARTY_LEADERS',
        'expected_is_escalation' => false,
        'notes' => 'NEGATIV — activitate parlamentară rutinieră. Confuzia potențială: „deputat + conferință de presă”.',
    ],
];
