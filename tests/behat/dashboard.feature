@mod @mod_projetvet @javascript
Feature: Dashboard page for DEVE overview

  In order to access DEVE metrics for students
  As an admin
  I need a dedicated Dashboard page in projetvet secondary navigation

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher1@example.com|
      | student1 | Student   | One      | student1@example.com|
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |

  Scenario: Open dashboard page from secondary navigation
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Dashboard" page
    Then I should see "Dashboard"
    And "Dashboard report container" "mod_projetvet > Dashboard report container" should exist
    And "Dashboard report container" "mod_projetvet > Dashboard report container" should contain "Number of projects"
    And "Dashboard report container" "mod_projetvet > Dashboard report container" should contain "Number of projects to validate"
    And "Dashboard report container" "mod_projetvet > Dashboard report container" should contain "Median ECTS"
