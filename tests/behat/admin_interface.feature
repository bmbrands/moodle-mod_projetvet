@mod @mod_projetvet
Feature: Admin interface for managing groups
  In order to manage student groups and teacher assignments
  As an editing teacher
  I need to be able to access the admin interface

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
      | teacher3 | Teacher   | Three    | teacher3@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | student3 | Student   | Three    | student3@example.com |
      | student4 | Student   | Four     | student4@example.com |
      | student5 | Student   | Five     | student5@example.com |
      | student6 | Student   | Six      | student6@example.com |
      | student7 | Student   | Seven    | student7@example.com |
      | student8 | Student   | Eight    | student8@example.com |
      | student9 | Student   | Nine     | student9@example.com |
      | student10| Student   | Ten      | student10@example.com|
      | student11| Student   | Eleven   | student11@example.com|
      | student12| Student   | Twelve   | student12@example.com|
      | student13| Student   | Thirteen | student13@example.com|
      | student14| Student   | Fourteen | student14@example.com|
      | student15| Student   | Fifteen  | student15@example.com|
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
      | student4 | C1     | student        |
      | student5 | C1     | student        |
      | student6 | C1     | student        |
      | student7 | C1     | student        |
      | student8 | C1     | student        |
      | student9 | C1     | student        |
      | student10| C1     | student        |
      | student11| C1     | student        |
      | student12| C1     | student        |
      | student13| C1     | student        |
      | student14| C1     | student        |
      | student15| C1     | student        |
    And the following "activities" exist:
      | activity   | course | name        | intro                  |
      | projetvet  | C1     | ProjetVet 1 | Test projetvet activity|

  Scenario: Navigate to admin page
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as teacher1
    When I follow "Admin"
    Then I should see "Manage student groups"
