@mod @mod_projetvet @javascript
Feature: Face-to-face session form operations in mod_projetvet

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student        |
    And the following "groups" exist:
      | name | description | course | idnumber |
      | Group 1 | G1 description | C1 | G1 |
    And the following "group members" exist:
      | user | group |
      | student1 | G1 |
      | teacher1 | G1 |
    And the following "activities" exist:
      | activity  | name            | course | idnumber    | groupmode |
      | projetvet | My Activities   | C1     | projetvet1  | 1         |

  Scenario: Student creates a new face-to-face session
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Face-to-Face Session" "button"
    And I wait until the page is ready
    Then I should see "Interview date"
    And I should see "Teacher One"

    # Fill in general information (entrystatus 0)
    When I set the following fields to these values:
      | Interview date                                                      | ##15 March 2025 10:00## |
      | Year of study in which the interview was conducted                  | Year 2                  |
      | Type of interview                                                   | Face-to-face            |

    # Fill in interview report (entrystatus 0)
    And I set the following fields to these values:
      | Report                                                              | We discussed my progress |
      | Personal notes                                                      | Remember to follow up    |

    And I submit the projetvet form
    And I wait until the page is ready

    # Verify table row shows correct values
    Then I should see "Interview submitted to tutor"
    And I close the notification alert
    And I should see "15/03/25"
    And I should see "Face-to-face to be confirmed by the tutor" in the "15/03/25" "table_row"
    And I should see "Year 2" in the "15/03/25" "table_row"
    And I should see "Face-to-face" in the "15/03/25" "table_row"
    And I should see "We discussed my progress" in the "15/03/25" "table_row"
  Scenario: Student creates draft, edits and submits face-to-face session
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Face-to-Face Session" "button"

    # Create draft (entrystatus 0)
    When I set the following fields to these values:
      | Interview date                                                      | ##20 March 2025 14:00## |
      | Year of study in which the interview was conducted                  | Year 2                  |
      | Type of interview                                                   | Video call              |
      | Report                                                              | Initial notes           |

    And I click on form button "Save as draft"
    And I wait until the page is ready

    Then I should see "20/03/25"
    And I should see "Editing in progress" in the "20/03/25" "table_row"

    # Edit and submit
    When I click on row with text "20/03/25"
    And I wait "1" seconds
    And I set the following fields to these values:
      | Report                                                              | Updated meeting notes   |

    And I submit the projetvet form
    And I wait until the page is ready
    Then I should see "Interview submitted to tutor"
    And I close the notification alert

    Then I should see "Updated meeting notes" in the "20/03/25" "table_row"
    And I should see "Face-to-face to be confirmed by the tutor" in the "20/03/25" "table_row"

  Scenario: Teacher validates face-to-face session
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Face-to-Face Session" "button"
    And I set the following fields to these values:
      | Interview date                                                      | ##25 March 2025 09:00## |
      | Year of study in which the interview was conducted                  | Year 4                  |
      | Type of interview                                                   | Phone call              |
      | Report                                                              | Session summary         |
    And I submit the projetvet form
    And I wait until the page is ready
    And I log out

    # Teacher validates (entrystatus 1)
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "25/03/25"
    And I wait "1" seconds
    And I submit the projetvet form
    And I wait until the page is ready

    Then I should see "Interview confirmed"
    And I close the notification alert
    And I should see "Face-to-face confirmed by the tutor" in the "25/03/25" "table_row"

  Scenario: Delete face-to-face session
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Face-to-Face Session" "button"
    And I set the following fields to these values:
      | Interview date                                                      | ##10 April 2025 11:00## |
      | Year of study in which the interview was conducted                  | Year 1                  |
      | Type of interview                                                   | Face-to-face            |
      | Report                                                              | Session to delete       |
    And I click on form button "Save as draft"
    And I wait until the page is ready

    Then I should see "10/04/25"

    # Delete the session
    When I click on "Actions" "button" in the "10/04/25" "table_row"
    And I click on "Delete" "link"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I wait until the page is ready

    Then I should not see "10/04/25"
