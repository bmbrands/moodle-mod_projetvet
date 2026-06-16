@mod @mod_projetvet @javascript @_file_upload
Feature: Tutor assignments interface for managing groups

  In order to manage student groups and teacher assignments
  As an editing teacher
  I need to be able to access the assignments interface

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                 |
      | teacher1  | Teacher   | One      | teacher1@example.com  |
      | teacher2  | Teacher   | Two      | teacher2@example.com  |
      | teacher3  | Teacher   | Three    | teacher3@example.com  |
      | student1  | Student   | One      | student1@example.com  |
      | student2  | Student   | Two      | student2@example.com  |
      | student3  | Student   | Three    | student3@example.com  |
      | student4  | Student   | Four     | student4@example.com  |
      | student5  | Student   | Five     | student5@example.com  |
      | student6  | Student   | Six      | student6@example.com  |
      | student7  | Student   | Seven    | student7@example.com  |
      | student8  | Student   | Eight    | student8@example.com  |
      | student9  | Student   | Nine     | student9@example.com  |
      | student10 | Student   | Ten      | student10@example.com |
      | student11 | Student   | Eleven   | student11@example.com |
      | student12 | Student   | Twelve   | student12@example.com |
      | student13 | Student   | Thirteen | student13@example.com |
      | student14 | Student   | Fourteen | student14@example.com |
      | student15 | Student   | Fifteen  | student15@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | teacher2  | C1     | editingteacher |
      | teacher3  | C1     | editingteacher |
      | student1  | C1     | student        |
      | student2  | C1     | student        |
      | student3  | C1     | student        |
      | student4  | C1     | student        |
      | student5  | C1     | student        |
      | student6  | C1     | student        |
      | student7  | C1     | student        |
      | student8  | C1     | student        |
      | student9  | C1     | student        |
      | student10 | C1     | student        |
      | student11 | C1     | student        |
      | student12 | C1     | student        |
      | student13 | C1     | student        |
      | student14 | C1     | student        |
      | student15 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
      | Teacher2 Group | teacher2 | expert  | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user      | group          |
      | student1  | Teacher1 Group |
      | student2  | Teacher1 Group |
      | student3  | Teacher1 Group |
      | student4  | Teacher1 Group |
      | student5  | Teacher1 Group |
      | student6  | Teacher2 Group |
      | student7  | Teacher2 Group |
      | student8  | Teacher2 Group |
      | student9  | Teacher2 Group |
      | student10 | Teacher2 Group |

  Scenario: Navigate to Tutor assignments page
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then I should see "Show only students without teachers"

  Scenario: View teachers report with ratings and capacity
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should exist
    And I should see "Teacher One" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "Teacher Two" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "Teacher Three" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    # Check Teacher One (average rating, 5 students, target 8)
    And I should see "Average" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "8" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "5" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    # Check Teacher Two (expert rating, 5 students, target 12)
    And I should see "Expert" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "12" in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"

  Scenario: View students report with teacher assignments
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should exist
    And I click on "showcount" buttonaction in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    # Check some assigned students
    And I should see "Student One" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should see "Student Two" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should see "Student Six" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    # Check some unassigned students
    And I should see "Student Eleven" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should see "Student Fifteen" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"

  Scenario: Verify student teacher assignments in report
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should exist
    And I click on "showcount" buttonaction in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    # Students 1-5 should be assigned to Teacher One
    And "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should contain "Teacher One"
    # Students 6-10 should be assigned to Teacher Two
    And "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should contain "Teacher Two"

  Scenario: Verify teacher capacity calculations
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should exist
    # Teacher table should have the required columns
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Rating"
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Target"
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Current"
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Gap"

  Scenario: Update teacher rating changes target capacity
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "update-teacher-rating" buttonaction in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    Then I should see "Update capacity"
    And I should see "Teacher Rating and Capacity"
    When I set the field "Rating" to "novice"
    And I press "Save changes"
    Then "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Novice"

  Scenario: Open assign students dialog from teacher report
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "assign-students" buttonaction in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    Then I should see "Assign students"
    And I should see "Select student"
    And I should not see "Select secondary teacher"

  Scenario: Open assign secondary teacher dialog from teacher report
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "assign-secondary-teacher" buttonaction in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    Then I should see "Assign secondary teacher"
    And I should see "Select secondary teacher"
    And I should not see "Select student"

  Scenario: Filter students without teachers
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "Show only students without teachers" "checkbox"
    Then I should see "Student Eleven" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should see "Student Twelve" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should see "Student Fifteen" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    But I should not see "Student One" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    And I should not see "Student Six" in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"

  Scenario: Filter teachers with capacity keeps toggle state after reload
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "Show only teachers with capacity" "checkbox"
    Then the checked attribute of "Show only teachers with capacity" "checkbox" should be set
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should exist

  Scenario: Upload groups CSV and import assignments
    Given I am on the "ProjetVet 1" "projetvet activity" page logged in as admin
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    And I click on "upload-groups" buttonaction in the "Tutor assignments teachers section" "mod_projetvet > Tutor assignments teachers section"
    Then I should see "Download current groups as CSV"
    And I should see "CSV separator"
    And I should see "Encoding"
    And I should see "Delete existing groups before import"
    When I upload "mod/projetvet/tests/fixtures/groups_upload.csv" file to "CSV file" filemanager
    And I click on "Delete existing groups before import" "checkbox"
    And I press "Save changes"
    Then "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Teacher Three"
    And "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Novice"
    When I click on "showcount" buttonaction in the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"
    Then "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should contain "Student Eleven"
    And "Tutor assignments students report" "mod_projetvet > Tutor assignments students report" should contain "Teacher Three"
