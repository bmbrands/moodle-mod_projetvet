@mod @mod_projetvet @javascript
Feature: Delete a project from the projects list

  In order to remove a project that should not exist
  As a student, a tutor or an administrator
  I need to delete it from the ⋮ menu of the projects list, after a confirmation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
      | manager1 | Manager   | One      | manager1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | manager1 | C1     | manager        |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group          |
      | student1 | Teacher1 Group |
    And the following "mod_projetvet > projects" exist:
      | projetvet  | student  | title                               | entrystatus |
      | projetvet1 | student1 | Présidence du Club Comédie Musicale | 0           |
      | projetvet1 | student1 | Stage rural                         | 1           |
      | projetvet1 | student1 | Projet validé                       | 4           |

  Scenario: The student deletes a draft project after the confirmation
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as student1
    When I click on "Actions" "button" in the "Présidence du Club Comédie Musicale" "table_row"
    And I click on "Delete" "link" in the "Présidence du Club Comédie Musicale" "table_row"
    Then I should see "The project “Présidence du Club Comédie Musicale” will be permanently deleted." in the "Delete this project?" "dialogue"
    And "Cancel" "button" should exist in the "Delete this project?" "dialogue"
    When I click on "Delete" "button" in the "Delete this project?" "dialogue"
    Then I should not see "Présidence du Club Comédie Musicale"
    And I should see "Stage rural"

  Scenario: Cancelling the confirmation keeps the project
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as student1
    When I click on "Actions" "button" in the "Stage rural" "table_row"
    And I click on "Delete" "link" in the "Stage rural" "table_row"
    And I click on "Cancel" "button" in the "Delete this project?" "dialogue"
    Then I should see "Stage rural"
    When I reload the page
    Then I should see "Stage rural"

  Scenario: The student cannot delete a project once it is accepted
    When I am on the "ProjetVet 1" "projetvet activity" page logged in as student1
    And I click on "Actions" "button" in the "Projet validé" "table_row"
    Then "Delete" "link" should not exist in the "Projet validé" "table_row"

  Scenario Outline: Teachers and administrators delete any project of a student
    Given I log in as "<user>"
    When I open the student info sheet for "student1" in "ProjetVet 1"
    And I click on "Actions" "button" in the "Projet validé" "table_row"
    And I click on "Delete" "link" in the "Projet validé" "table_row"
    Then I should see "The project “Projet validé” will be permanently deleted." in the "Delete this project?" "dialogue"
    When I click on "Delete" "button" in the "Delete this project?" "dialogue"
    Then I should not see "Projet validé"
    And I should see "Stage rural"

    Examples:
      | user     |
      | teacher1 |
      | teacher2 |
      | manager1 |
