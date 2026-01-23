# Groups API Implementation - Student Count Fix

## Problem

When a teacher was assigned as a secondary tutor to another teacher's group, the admin_teachers report showed incorrect student counts. For example:
- Teacher A (user 22) owns group 6 with 4 students
- Teacher A is also a secondary tutor in group 7 (owned by Teacher B) with 3 students
- The report was showing Teacher A had 7 students (incorrect)

The issue was that the report counted ALL students where the teacher had ANY tutoring role, but for capacity planning purposes, we should only count students in groups where the teacher is the PRIMARY owner.

## Solution

### 1. Created New Groups API (`classes/local/api/groups.php`)

A centralized API for all group-related operations with clear separation between:

**Primary Student Count** (for capacity planning):
- `get_primary_student_count()` - Only counts students in groups where user is PRIMARY owner
- `get_teacher_available_capacity()` - Calculates available slots (target - primary students)
- `get_teacher_statistics()` - Full statistics including primary/secondary/total counts

**All Tutored Students** (for general viewing):
- `get_all_tutored_student_count()` - Counts students where user is ANY type of tutor

**Group Access Methods**:
- `get_owned_groups()` - Groups where user is primary tutor
- `get_secondary_tutor_groups()` - Groups where user helps as secondary tutor
- `get_student_groups()` - Groups where user is a student

### 2. Enhanced Persistent Classes

**projetvet_group.php** - Added helper methods:
- `get_student_count()` - Count students in this specific group
- `get_tutor_count()` - Count tutors (primary + secondary)
- `get_primary_tutor()` - Get the group owner
- `get_secondary_tutors()` - Get all secondary tutors

**teacher_rating.php** - Created new persistent class:
- Manages teacher capacity ratings (expert/average/novice)
- Provides capacity values (12/8/5 students)
- Helper methods for rating checks

### 3. Updated Admin Teachers Report

The report now uses the Groups API:
- **Rating column**: Uses `teacher_rating::get_or_create_rating()`
- **Target column**: Uses `teacher_rating->get_capacity()`
- **Current column**: Uses `groups::get_primary_student_count()` (PRIMARY ONLY)
- **Gap column**: Uses `groups::get_teacher_available_capacity()` (PRIMARY ONLY)

## Key Principle

**Capacity Planning vs. Responsibility**:
- **Capacity/Target calculations**: Only count PRIMARY owned groups
- **Viewing/Responsibility**: Can show ALL tutored groups

This ensures:
1. Teacher A's capacity is based only on their own group (4 students)
2. Teacher A can still see/help with students in groups where they're secondary tutor
3. Capacity planning remains accurate for assignment purposes

## Files Created/Modified

**Created**:
- `classes/local/api/groups.php` - New Groups API
- `classes/local/persistent/teacher_rating.php` - New persistent class

**Modified**:
- `classes/reportbuilder/local/systemreports/admin_teachers.php` - Uses Groups API
- `classes/local/persistent/projetvet_group.php` - Added helper methods

**Not Modified** (working correctly):
- `classes/form/edit_member_form.php` - Member editing works fine
- `classes/output/group_members_table.php` - Display works fine
- `classes/utils.php` - Utility functions work fine

## Usage Examples

```php
// Get student count for capacity planning (primary groups only)
$count = \mod_projetvet\local\api\groups::get_primary_student_count($teacherid, $projetvetid);

// Get all students teacher is responsible for (any tutor role)
$totalcount = \mod_projetvet\local\api\groups::get_all_tutored_student_count($teacherid, $projetvetid);

// Get available capacity
$available = \mod_projetvet\local\api\groups::get_teacher_available_capacity($teacherid, $projetvetid);

// Get full statistics
$stats = \mod_projetvet\local\api\groups::get_teacher_statistics($teacherid, $projetvetid);
// Returns: rating, target, primary_count, secondary_count, total_count, gap
```

## Testing Scenarios

With the example data:
- User 22 is primary tutor of group 6 (4 students)
- User 22 is secondary tutor of group 7 (3 students)
- User 22 has "average" rating (capacity: 8)

**Results**:
- **Target**: 8 (from rating)
- **Current**: 4 (only primary group students)
- **Gap**: 4 (can take 4 more students)
- **Total Tutored**: 7 (for information only)

This is correct! Teacher 22 can take 4 more students in their own group, even though they're helping with 3 others.
