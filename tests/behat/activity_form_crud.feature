@mod @mod_projetvet @javascript
Feature: Activity form CRUD operations in mod_projetvet

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
      | student3 | Student   | Three    | student3@example.com |
      | student4 | Student   | Four     | student4@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "activities" exist:
      | activity  | name            | course | idnumber    |
      | projetvet | My Activities   | C1     | projetvet1  |
    And the following "mod_projetvet > projetvet groups" exist:
      | name    | teacher  | rating  | projetvetidnumber | course |
      | Group 1 | teacher1 | average | projetvet1        | C1     |
      | Group 2 | teacher2 | average | projetvet1        | C1     |
    And the following "mod_projetvet > projetvet group members" exist:
      | user     | group   |
      | student1 | Group 1 |
      | student2 | Group 1 |
      | student3 | Group 2 |
      | student4 | Group 2 |

  Scenario: Student creates a new activity with all fields
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    Then I should see "General information"

    # Fill in general information category (entrystatus 0)
    When I set the following fields to these values:
      | Activity title                                                      | My First Activity       |
      | Summary description                                                 | This is a test activity |
      | Year of study in which the project will be completed                | Year 2                  |
      | Rank                                                                | A                       |
      | Expected workload (approximately)                                   | 40                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    # Submit to tutor (changes status to 1)
    And I submit the projetvet form
    And I close the notification alert

    Then I should see "My First Activity"

  Scenario: Student creates, saves as draft, then edits and submits
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"

    # Create initial draft
    When I set the following fields to these values:
      | Activity title                                                      | Draft Activity          |
      | Summary description                                                 | Initial draft content   |
      | Expected workload (approximately)                                   | 20                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    And I click on "Save as draft" "button"

    Then I should see "Draft Activity"

    # Edit the draft
    When I click on row with text "Draft Activity"
    And I set the following fields to these values:
      | Activity title                                                      | Updated Activity        |
      | Summary description                                                 | Updated content         |

    And I submit the projetvet form

    Then I should see "Your project has been submitted"
    And I close the notification alert
    And I should see "Project submitted – awaiting eligibility"

  Scenario: Teacher accepts student activity
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Activity for Approval   |
      | Summary description                                                 | Please review           |
      | Expected workload (approximately)                                   | 30                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    And I submit the projetvet form
    And I log out

    # Teacher reviews and accepts
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "Activity for Approval"

    # Add acceptance comments (entrystatus 1)
    And I set the following fields to these values:
      | Comments | Good work, approved |
      | Based on the discussion with your tutored student and the indicative estimate, indicate the potential number of ECTS credits for this activity. This number will be reassessed at the end of the activity, taking into account the actual hours worked | 2 |

    And I submit the projetvet form

    Then I should see "Eligibility validated"
    And I close the notification alert

    # Verify we're back at the entry list and status is updated
    And I should see "Activity for Approval"
    And I should see "Report being written"

  Scenario: Student completes activity report after acceptance
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Almost there         |
      | Summary description                                                 | To be completed         |
      | Expected workload (approximately)                                   | 25                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    And I submit the projetvet form
    And I log out

    # Teacher reviews and accepts
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "Almost there"

    # Add acceptance comments (entrystatus 1)
    And I set the following fields to these values:
      | Comments | Good work, approved |
      | Based on the discussion with your tutored student and the indicative estimate, indicate the potential number of ECTS credits for this activity. This number will be reassessed at the end of the activity, taking into account the actual hours worked | 2 |

    And I submit the projetvet form
    And I close the notification alert

    # Verify we're back at the entry list and status is updated
    Then I should see "Almost there"
    And I should see "Report being written"

    # Student adds completion report (entrystatus 2)
    When I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on row with text "Almost there"
    And I set the following fields to these values:
      | Start date of completion                                            | ##1 January 2025##      |
      | End date of completion                                              | ##31 March 2025##       |
      | Summary of main achievements and actions carried out during this project | I completed all tasks   |
      | Number of hours completed (modify if necessary)                     | 25                      |

    And I submit the projetvet form
    Then I should see "Project report submitted to the supervisor"
    And I close the notification alert

    And I should see "Almost there"
    And I should see "Report submitted – awaiting validation"

  Scenario: Teacher provides final validation
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Final Validation Test   |
      | Summary description                                                 | Ready for validation    |
      | Expected workload (approximately)                                   | 30                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    And I submit the projetvet form
    And I log out

    # Teacher reviews and accepts (entrystatus 1)
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "Final Validation Test"

    And I set the following fields to these values:
      | Comments | Good work, approved |
      | Based on the discussion with your tutored student and the indicative estimate, indicate the potential number of ECTS credits for this activity. This number will be reassessed at the end of the activity, taking into account the actual hours worked | 2 |

    And I submit the projetvet form
    And I log out

    # Student adds completion report (entrystatus 2)
    When I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on row with text "Final Validation Test"
    And I set the following fields to these values:
      | Start date of completion                                            | ##1 February 2025##     |
      | End date of completion                                              | ##30 April 2025##       |
      | Summary of main achievements and actions carried out during this project | All objectives achieved |
      | Number of hours completed (modify if necessary)                     | 30                      |

    And I submit the projetvet form
    And I log out

    # Teacher provides final validation (entrystatus 3)
    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on row with text "Final Validation Test"

    And I set the following fields to these values:
      | Final comments    | Excellent work completed |
      | Final assessment  | Project objectives fully achieved and skills clearly developed |
      | Final number of ECTS | 3                     |

    And I submit the projetvet form

    Then I should see "Project validated – ECTS credits awarded"
    And I close the notification alert
    And I should see "Project validated – ECTS awarded"

  Scenario: Delete activity entry
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "New Activity" "button"
    And I set the following fields to these values:
      | Activity title                                                      | Activity to Delete      |
      | Summary description                                                 | Will be deleted         |
      | Expected workload (approximately)                                   | 10                      |

    # Select category using tagselect
    And I open tagselect for "Category"
    And I select tag "Stage en clinique vétérinaire canine" in tagselect popup
    And I save tags in tagselect popup

    # Select competences using tagselect
    And I open tagselect for "Competencies (2 required minimum)"
    And I select tag "COMM3 - Communiquer en contexte international ou interculturel" in tagselect popup
    And I select tag "D5- Pratiquer un examen post-mortem" in tagselect popup
    And I save tags in tagselect popup

    And I click on "Save as draft" "button"

    Then I should see "Activity to Delete"

    # First click on the row containing "Activity to Delete" to find the dropdown, then delete
    When I click on "Actions" "button" in the "Activity to Delete" "table_row"
    And I click on "Delete" "link"
    And I click on "Delete" "button" in the "Confirm" "dialogue"

    Then I should not see "Activity to Delete"

  Scenario: Student can edit thesis and mobility modals
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    When I click on "Set subject" "button"
    Then the "field_thesissubject_field" "field" should be enabled
    And I set the following fields to these values:
      | field_thesissubject_field | Student thesis subject |
      | field_thesisdirector      | Dr Tutor               |
    And I click on form button "Save"
    And I should see "Student thesis subject"

    When I click on "Set title" "button"
    Then the "field_mobilitylocation" "field" should be enabled
    And I click on "field_mobilityerasmus" "checkbox"
    And I set the following fields to these values:
      | field_mobilitylocation | Paris, France |
    And I click on form button "Save"
    And I should see "Realized"

  Scenario: Teacher sees thesis modal as readonly
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    When I click on "Set subject" "button"
    And I set the following fields to these values:
      | field_thesissubject_field | Thesis visible to teacher |
    And I click on form button "Save"
    And I log out

    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "View" "button" in the "Thesis subject" "table_row"
    Then the "field_thesissubject_field" "field" should be readonly

  Scenario: Teacher sees mobility modal as readonly
    Given I am on the "My Activities" "projetvet activity" page logged in as "student1"
    When I click on "Set title" "button"
    And I click on "field_mobilityfmp" "checkbox"
    And I set the following fields to these values:
      | field_mobilitylocation | Lyon, France |
    And I click on form button "Save"
    And I log out

    When I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    And I view activities for student "Student One"
    And I click on "View" "button" in the "International mobility" "table_row"
    Then the "field_mobilitylocation" "field" should be disabled

  Scenario: Practical info is editable by teacher and readonly for student
    Given I am on the "My Activities" "projetvet activity" page logged in as "teacher1"
    When I view activities for student "Student One"
    And I click on "Practical Info" "button"
    Then the "field_teacher_info" "field" should be enabled
    And I set the following fields to these values:
      | field_teacher_info | Teacher-only practical notes |
    And I click on form button "Save"
    And I log out

    When I am on the "My Activities" "projetvet activity" page logged in as "student1"
    And I click on "Practical Info" "button"
    Then the "field_teacher_info" "field" should be readonly
