# Projetvet Groepen Systeem

## Overzicht

Het projetvet groepen systeem biedt een flexibel alternatief voor Moodle's standaard groepen systeem, met ondersteuning voor:

- **Primaire en secundaire tutoren**: Onderscheid tussen vaste tutors en tijdelijke/co-tutoren
- **Type lidmaatschap**: `primary_tutor`, `secondary_tutor`, of `student`

## Database Structuur

### projetvet_groups
Bevat de groepen per projetvet instance.

- `id`: Primary key
- `projetvetid`: Link naar projetvet instance
- `name`: Naam van de groep
- `description`: Beschrijving
- `ownerid`: User ID van de primaire tutor/eigenaar

### projetvet_group_members
Bevat het lidmaatschap met uitgebreide opties.

- `id`: Primary key
- `groupid`: Link naar projetvet_groups
- `userid`: Link naar user
- `membertype`: Type lidmaatschap (`primary_tutor`, `secondary_tutor`, `student`)

## Gebruik

### 1. Groepen Aanmaken voor Tutoren

Gebruik het CLI script om automatisch voor elke tutor een groep aan te maken:

```bash
# Via course module ID
php mod/projetvet/cli/create_tutor_groups.php --cmid=123

# Via projetvet instance ID
php mod/projetvet/cli/create_tutor_groups.php --projetvetid=5

# Force hercreaturen van groepen
php mod/projetvet/cli/create_tutor_groups.php --cmid=123 --force
```

### 2. Persistent Classes Gebruiken

#### Groep maken en beheren

```php
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\group_member;

// Nieuwe groep maken
$group = new projetvet_group(0, (object)[
    'projetvetid' => $projetvetid,
    'name' => 'Groep - Dr. Smith',
    'description' => 'Tutor groep voor Dr. Smith',
    'ownerid' => $tutorid,
]);
$group->create();

// Student toevoegen
$group->add_member($studentid, group_member::TYPE_STUDENT);

// Secundaire tutor toevoegen
$group->add_member($temptutorid, group_member::TYPE_SECONDARY_TUTOR);

// Groepen ophalen voor een projetvet instance
$groups = projetvet_group::get_by_projetvet($projetvetid);

// Groepen waar een user lid van is
$usergroups = projetvet_group::get_by_member($userid, $projetvetid);

// Aantal leden ophalen
$studentcount = $group->get_member_count(group_member::TYPE_STUDENT);
$tutorcount = $group->get_member_count(group_member::TYPE_SECONDARY_TUTOR);

// Alle studenten ophalen (alleen actieve)
$students = $group->get_members(group_member::TYPE_STUDENT, true);

// Check of iemand lid is
if ($group->is_member($userid, group_member::TYPE_STUDENT, true)) {
    echo "Is actief student lid";
}

// Lid verwijderen
$group->remove_member($userid);
```

#### Lidmaatschap beheren

```php
use mod_projetvet\local\persistent\group_member;

// Alle groepen voor een user
$memberships = group_member::get_user_memberships(
    $userid,
    $projetvetid,
    group_member::TYPE_STUDENT,
    true  // alleen actieve
);

// Specifiek lidmaatschap ophalen
$membership = group_member::get_membership($groupid, $userid);

if ($membership) {
    // Check type
    if ($membership->is_primary_tutor()) {
        echo "Primaire tutor";
    }

    if ($membership->is_secondary_tutor()) {
        echo "Secundaire tutor";
    }

    // Check actief
    if ($membership->is_active()) {
        echo "Lidmaatschap is actief";
    }
}

// Direct lidmaatschap maken
$member = new group_member(0, (object)[
    'groupid' => $groupid,
    'userid' => $userid,
    'membertype' => group_member::TYPE_SECONDARY_TUTOR,
]);
$member->create();
```

## Member Types

- **`group_member::TYPE_PRIMARY_TUTOR`** (`primary_tutor`): Primaire/vaste tutor, eigenaar van de groep
- **`group_member::TYPE_SECONDARY_TUTOR`** (`secondary_tutor`): Secundaire/tijdelijke tutor
- **`group_member::TYPE_STUDENT`** (`student`): Student

## Veel Voorkomende Scenarios

### Scenario 1: Studenten Toewijzen aan Tutor

```php
// Haal groep van tutor op
$groups = projetvet_group::get_by_owner($tutorid, $projetvetid);
$group = reset($groups);

// Voeg meerdere studenten toe
foreach ($studentids as $studentid) {
    $group->add_member($studentid, group_member::TYPE_STUDENT);
}
```

### Scenario 2: Actieve Tutoren voor een Student

```php
// Haal alle groepen waar student lid van is
$groups = projetvet_group::get_by_member($studentid, $projetvetid);

$tutors = [];
foreach ($groups as $group) {
    // Haal alle tutoren (primair en secundair) die actief zijn
    $groupmembers = $group->get_members(null, true);

    foreach ($groupmembers as $member) {
        if ($member->is_tutor()) {
            $tutors[] = $member->get_user();
        }
    }
}
```

## Volgende Stappen

- [ ] UI bouwen voor groepsbeheer
- [ ] UI voor studenten toewijzen aan groepen
- [ ] Rapportage: studenten per tutor
- [ ] Integratie met bestaande utils::get_student_tutor() functie
