# Assessment Question Management System Setup

## Database Setup Required

Before using the question management feature, you need to add a JSON column to the assessments table.

### Step 1: Run the SQL Script

Execute the following SQL script in your MySQL database:

```sql
-- Run this in your lms_neust_normalized database
SOURCE database/add_question_tables.sql;
```

Or manually run the SQL command from `database/add_question_tables.sql`:

```sql
-- Add questions JSON column to assessments table for normalized storage
ALTER TABLE `assessments` 
ADD COLUMN IF NOT EXISTS `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of questions with options';
```

### Step 2: Verify Setup

After running the SQL script, you should have:
- `questions` JSON column added to the `assessments` table
- All assessment data stored in a normalized JSON format

## Features Available

### Question Types Supported:
1. **Multiple Choice** - 4 options with one correct answer
2. **True/False** - Two options (True/False)
3. **Identification** - Text input for correct answer

### Question Management Features:
- ✅ Add new questions to assessments
- ✅ Edit existing questions
- ✅ Delete questions
- ✅ Set question points/weight
- ✅ Reorder questions automatically
- ✅ Support for all question types

### How to Use:
1. Go to any module's assessments page
2. Click the "Questions" button on any assessment card
3. Use the "Add Question" button to create new questions
4. Edit or delete questions using the action buttons
5. Questions are automatically saved and integrated with the assessment system

## Integration with Existing System

The question management system is fully integrated with:
- ✅ JSON-based assessment storage (questions stored within assessments)
- ✅ Normalized JSON structure for all data
- ✅ CSRF protection
- ✅ User-friendly interface
- ✅ Real-time updates via AJAX

## Notes

- Questions are stored as JSON arrays within each assessment
- All assessment data (metadata + questions) is stored in JSON format
- This approach is more normalized and consistent
- The system maintains backward compatibility
- All existing assessment functionality continues to work
- No separate database tables needed - everything is self-contained
