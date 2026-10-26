@mod @mod_projetvet
Feature: Tutors and students see the temporary substitute tutors

  In order to know who follows a student
  As a tutor or a student
  I need to see the principal tutor and the active substitute tutor

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
      | teacher3 | Teacher   | Three    | teacher3@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | student3 | Student   | Three    | student3@example.com |
      | student5 | Student   | Five     | student5@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | teacher3 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student5 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
      | Teacher2 Group | teacher2 | average | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group          |
      | student1 | Teacher1 Group |
      | student2 | Teacher1 Group |
      | student3 | Teacher1 Group |
      | student5 | Teacher2 Group |
    # Student One: substitution in effect; Student Two: planned substitution; Student Three: closed substitution.
    And the following "mod_projetvet > substitutions" exist:
      | student  | substitute | projetvetidnumber | startdate     | status |
      | student1 | teacher2   | projetvet1        | ##yesterday## | open   |
      | student2 | teacher3   | projetvet1        | ##+1 week##   | open   |
      | student3 | teacher2   | projetvet1        | ##-1 week##   | closed |

  Scenario: The substitute tutor sees the students they follow as a substitute
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as teacher2
    Then I should see "Substitution" in the "Student One" "table_row"
    And I should see "Replacing Teacher One (since" in the "Student One" "table_row"
    And I should see "Principal tutor" in the "Student Five" "table_row"
    And I should not see "Student Two"
    And I should not see "Student Three"

  Scenario: A planned substitution is shown with its start date
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as teacher3
    Then I should see "Replacing Teacher One (from" in the "Student Two" "table_row"
    And I should not see "Student One"

  Scenario: The principal tutor keeps their students and sees the active substitutes
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as teacher1
    Then I should see "Temporarily followed by Teacher Two (since" in the "Student One" "table_row"
    And I should see "Temporarily followed by Teacher Three (from" in the "Student Two" "table_row"
    And I should see "Principal tutor" in the "Student Three" "table_row"
    And I should not see "Temporarily followed by" in the "Student Three" "table_row"

  Scenario: The student page shows the principal tutor and the substitute tutor
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as student1
    Then I should see "Teacher One" in the "Tutor" "table_row"
    And I should see "Teacher Two (since" in the "Substitute tutor" "table_row"

  Scenario: Without open substitution the student page shows no substitute tutor
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as student3
    Then I should see "Teacher One" in the "Tutor" "table_row"
    And I should not see "Substitute tutor"
