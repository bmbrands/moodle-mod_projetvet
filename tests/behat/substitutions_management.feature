@mod @mod_projetvet
Feature: Manage the temporary substitute tutors of a principal tutor's students

  In order to replace an unavailable principal tutor
  As a manager
  I need to assign substitute tutors per student, and close, extend or make the substitutions definitive

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name      |
      | text     | promotion | Promotion |
    And the following "users" exist:
      | username | firstname | lastname | email                | profile_field_promotion |
      | teacher1 | Teacher   | One      | teacher1@example.com |                         |
      | teacher2 | Teacher   | Two      | teacher2@example.com |                         |
      | teacher3 | Teacher   | Three    | teacher3@example.com |                         |
      | teacher4 | Teacher   | Four     | teacher4@example.com |                         |
      | student1 | Student   | One      | student1@example.com | A1                      |
      | student2 | Student   | Two      | student2@example.com | A3                      |
      | student3 | Student   | Three    | student3@example.com | A4                      |
      | student4 | Student   | Four     | student4@example.com | A2                      |
      | student5 | Student   | Five     | student5@example.com |                         |
      | student6 | Student   | Six      | student6@example.com |                         |
      | student7 | Student   | Seven    | student7@example.com |                         |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | teacher3 | C1     | editingteacher |
      | teacher4 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
      | student5 | C1     | student        |
      | student6 | C1     | student        |
      | student7 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | name        | intro                   | idnumber   |
      | projetvet | C1     | ProjetVet 1 | Test projetvet activity | projetvet1 |
    And the following "mod_projetvet > projetvet groups" exist:
      | name           | teacher  | rating  | projetvetidnumber | course |
      | Teacher1 Group | teacher1 | average | projetvet1        | C1     |
      | Teacher2 Group | teacher2 | novice  | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group          |
      | student1 | Teacher1 Group |
      | student2 | Teacher1 Group |
      | student3 | Teacher1 Group |
      | student4 | Teacher1 Group |
      | student5 | Teacher2 Group |
      | student6 | Teacher2 Group |
      | student7 | Teacher2 Group |
    And the following "mod_projetvet > tutor settings" exist:
      | user     | projetvetidnumber | status      | acceptsa1 |
      | teacher1 | projetvet1        | unavailable | yes       |
      | teacher2 | projetvet1        | open        | yes       |
      | teacher3 | projetvet1        | open        | no        |
      | teacher4 | projetvet1        | unavailable | yes       |

  @javascript
  Scenario: Open the substitute tutors page from the tutors table
    Given I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page logged in as admin
    Then "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report" should contain "Open substitutions"
    And I should not see "Secondary tutor(s)"
    And "[data-action='assign-secondary-teacher']" "css_element" should not exist
    When I click on "manage-substitutions" buttonaction in the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    Then I should see "Substitute tutors – Teacher Four"
    And I should see "Temporarily unavailable"

  Scenario: The substitute tutors page lists the students of the principal tutor and the possible substitutes
    When I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page logged in as admin
    Then I should see "Substitute tutors – Teacher One"
    And I should see "Student One (A1)"
    And I should see "Student Four (A2)"
    And I should not see "Student Five"
    And the "Substitute tutor" select box should contain "Teacher Two – Open – places left: 2 – A1: Yes"
    And the "Substitute tutor" select box should contain "Teacher Three – Open – places left: 8 – A1: No"
    And the "Substitute tutor" select box should contain "Teacher Four – Temporarily unavailable – places left: 8 – A1: Yes"
    And the "Substitute tutor" select box should not contain "Teacher One – Temporarily unavailable – places left: 4 – A1: Yes"
    And I should see "No open substitution."
    And I should see "No substitution yet."

  Scenario: Conflicts are reported and nothing is saved
    Given I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page logged in as admin
    # A1 student and a substitute refusing A1 students.
    When I set the following fields to these values:
      | Student One (A1) | 1                                              |
      | Student Two (A3) | 1                                              |
      | Substitute tutor | Teacher Three – Open – places left: 8 – A1: No |
      | Reason           | Sick leave                                     |
    And I press "Validate the substitutions"
    Then I should see "the student is in A1 and Teacher Three does not accept A1 students."
    And I should see "No open substitution."
    # Unavailable substitute.
    When I set the following fields to these values:
      | Student One (A1) | 0                                                                |
      | Substitute tutor | Teacher Four – Temporarily unavailable – places left: 8 – A1: Yes |
    And I press "Validate the substitutions"
    Then I should see "Teacher Four cannot take substitutions (status: Temporarily unavailable)."
    # Capacity overrun must be accepted explicitly.
    When I set the following fields to these values:
      | Student Two (A3)   | 1                                             |
      | Student Three (A4) | 1                                             |
      | Student Four (A2)  | 1                                             |
      | Substitute tutor   | Teacher Two – Open – places left: 2 – A1: Yes |
    And I press "Validate the substitutions"
    Then I should see "Teacher Two has 2 place(s) left for 3 student(s)."
    And I should see "No open substitution."
    When I set the field "Capacity" to "1"
    And I press "Validate the substitutions"
    Then I should see "3 substitution(s) saved."

  Scenario: Nothing selected is refused
    Given I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page logged in as admin
    When I set the following fields to these values:
      | Substitute tutor | Teacher Two – Open – places left: 2 – A1: Yes |
      | Reason           | Sick leave                                    |
    And I press "Validate the substitutions"
    Then I should see "Select at least one student."

  Scenario: Assign substitutes per student, in batch and individually
    Given I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page logged in as admin
    When I set the following fields to these values:
      | Student Two (A3)   | 1                                             |
      | Student Three (A4) | 1                                             |
      | Substitute tutor   | Teacher Two – Open – places left: 2 – A1: Yes |
      | Reason             | Sick leave                                    |
    And I press "Validate the substitutions"
    Then I should see "2 substitution(s) saved."
    And I should see "Student Two – substitute: Teacher Two"
    And I should see "Student Three – substitute: Teacher Two"
    And I should not see "Student Two (A3)"
    When I set the following fields to these values:
      | Student Four (A2) | 1                                              |
      | Substitute tutor  | Teacher Three – Open – places left: 8 – A1: No |
      | Reason            | Sick leave                                     |
    And I press "Validate the substitutions"
    Then I should see "1 substitution(s) saved."
    And I should see "Student Four – substitute: Teacher Three"
    And the following should exist in the "projetvet-substitution-history" table:
      | Student       | Substitute tutor | Status | Reason     |
      | Student Two   | Teacher Two      | Open   | Sick leave |
      | Student Three | Teacher Two      | Open   | Sick leave |
      | Student Four  | Teacher Three    | Open   | Sick leave |
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then I should see "5 (incl. 2 as substitute)" in the "Teacher Two" row of the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "1 (incl. 1 as substitute)" in the "Teacher Three" row of the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should see "3" in the "Teacher One" row of the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    And I should not see "Available again" in the "Teacher One" row of the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"

  Scenario: Close, extend and make substitutions definitive
    Given the following "mod_projetvet > substitutions" exist:
      | student  | substitute | projetvetidnumber | startdate      | reason     |
      | student2 | teacher2   | projetvet1        | ##yesterday##  | Sick leave |
      | student3 | teacher2   | projetvet1        | ##yesterday##  | Sick leave |
      | student4 | teacher3   | projetvet1        | ##yesterday##  | Sick leave |
    And I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page logged in as admin
    # Close one substitution: the student returns to the principal tutor.
    When I set the field "Student Two – substitute: Teacher Two" to "1"
    And I set the field "Notifications" in the "#id_manageheader" "css_element" to "0"
    And I press "Close the selected substitutions"
    Then I should see "1 substitution(s) closed: the students are back with their principal tutor."
    And I should see "Student Two (A3)"
    And I should not see "Student Two – substitute: Teacher Two"
    And the following should exist in the "projetvet-substitution-history" table:
      | Student     | Substitute tutor | Status | Closed by  |
      | Student Two | Teacher Two      | Closed | Admin User |
    # Extend a substitution.
    When I set the following fields to these values:
      | Student Three – substitute: Teacher Two | 1    |
      | newenddate[enabled]                     | 1    |
      | newenddate[day]                         | 31   |
      | newenddate[month]                       | 12   |
      | newenddate[year]                        | 2049 |
    And I press "Extend the selected substitutions"
    Then I should see "1 substitution(s) extended."
    And I should see "planned end 31/12/49"
    # Make a substitution definitive, after a confirmation.
    When I set the field "Student Four – substitute: Teacher Three" to "1"
    And I press "Make definitive"
    Then I should see "Student Four → Teacher Three"
    When I press "Continue"
    Then I should see "1 substitution(s) made definitive."
    And the following should exist in the "projetvet-substitution-history" table:
      | Student      | Substitute tutor | Status          |
      | Student Four | Teacher Three    | Made definitive |
    And I should not see "Student Four (A2)"
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page
    Then I should see "Teacher Three" in the "Student Four" row of the "Tutor assignments students report" "mod_projetvet > Tutor assignments students report"

  Scenario: The return of the principal tutor is flagged
    Given the following "mod_projetvet > substitutions" exist:
      | student  | substitute | projetvetidnumber | startdate     |
      | student2 | teacher2   | projetvet1        | ##yesterday## |
    And the following "mod_projetvet > tutor settings" exist:
      | user     | projetvetidnumber | status |
      | teacher1 | projetvet1        | open   |
    When I am on the "ProjetVet 1" "mod_projetvet > Tutor assignments" page logged in as admin
    Then I should see "Available again" in the "Teacher One" row of the "Tutor assignments teachers report" "mod_projetvet > Tutor assignments teachers report"
    When I am on the "ProjetVet 1 > teacher1" "mod_projetvet > Substitutions" page
    Then I should see "Teacher One is available again: 1 substitution(s) of their students are still open."
