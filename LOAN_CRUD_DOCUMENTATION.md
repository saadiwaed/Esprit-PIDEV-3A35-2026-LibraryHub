# Loan CRUD Implementation - Complete Documentation

## Overview

This is a production-ready, fully-featured CRUD (Create, Read, Update, Delete) implementation for the `Loan` entity in Symfony 6.4. It follows Symfony best practices and includes form handling, validation, pagination, and comprehensive repository methods.

## Project Structure

```
src/
├── Controller/
│   └── LoanController.php          # Main CRUD controller
├── Entity/
│   ├── Loan.php                    # Main entity with relationships
│   ├── BookCopy.php                # Related entity (enhanced with __toString)
│   ├── User.php                    # Related entity (enhanced with __toString)
│   ├── Penalty.php                 # Related entity (one-to-many from Loan)
│   └── Renewal.php                 # Related entity (one-to-many from Loan)
├── Enum/
│   ├── LoanStatus.php              # Enum: ACTIVE, RETURNED, OVERDUE
│   └── PaymentStatus.php           # Enum: UNPAID, PAID, PARTIAL
├── Form/
│   └── LoanType.php                # Symfony form class
└── Repository/
    └── LoanRepository.php          # Enhanced with custom query methods

templates/
└── loan/
    ├── index.html.twig             # Loan listing with pagination
    ├── show.html.twig              # Detailed loan view
    ├── new.html.twig               # Create new loan
    ├── edit.html.twig              # Edit existing loan
    ├── _form.html.twig             # Shared form template
    └── _delete_form.html.twig      # Delete confirmation section
```

## Features

### 1. **Controller (LoanController.php)**

#### Routes
- `GET /loan/` - List all loans with pagination
- `GET /loan/{id}` - View loan details
- `GET /loan/new` - Create new loan form
- `POST /loan/new` - Submit new loan
- `GET /loan/{id}/edit` - Edit loan form
- `POST /loan/{id}/edit` - Submit loan update
- `POST /loan/{id}` - Delete loan

#### Methods

**index()** - List loans with pagination
- Supports pagination with `?page=` parameter
- Customizable limit with `?limit=` parameter
- Default: 10 items per page
- Ordered by checkout time (descending)

**show()** - Display loan details
- Shows all loan information
- Displays related penalties and renewals
- Shows member information
- Shows associated book copy

**new()** - Create loan
- Renders form template
- Validates form submission
- Persists to database
- Redirects to show page on success

**edit()** - Edit loan
- Pre-populates form with current data
- Validates form submission
- Updates database
- Redirects to show page on success

**delete()** - Delete loan
- CSRF token validation for security
- Soft confirmation via JavaScript
- Removes associated penalties and renewals (cascade delete)

### 2. **Form (LoanType.php)**

Field configuration:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| checkoutTime | DateTimeType | Yes | Date and time picker |
| dueDate | DateType | Yes | Date picker |
| returnDate | DateTimeType | No | Nullable, can be updated later |
| status | ChoiceType (Enum) | Yes | ACTIVE, RETURNED, OVERDUE |
| renewalCount | IntegerType | No | Default: 0, minimum 0 |
| lateFee | FloatType | No | Default: 0.0, step 0.01 |
| notes | TextareaType | No | Additional comments (5 rows) |
| bookCopy | EntityType | Yes | Select from available copies |
| member | EntityType | Yes | Select registered member |

### 3. **Templates**

#### index.html.twig
- Responsive table with Bootstrap 5
- Columns: ID, Book Copy, Member, Checkout, Due Date, Return Date, Status, Late Fee, Actions
- Status badges: Green (Active), Red (Overdue), Gray (Returned)
- Action buttons: View, Edit, Delete
- Pagination controls
- Empty state message
- Flash messages for success/error

#### show.html.twig
- Comprehensive loan details
- Main information card with all fields
- Penalties section (if any)
- Renewal history (if any)
- Sidebar: Member information
- Sidebar: Book copy information
- Delete button in danger zone
- Back and edit navigation buttons

#### new.html.twig & edit.html.twig
- Form layout with 2-column responsive design
- Full form with all fields
- Form reset button
- Back to list navigation
- Validation error display
- Success/error flash messages

#### _form.html.twig
- Shared form template used by new and edit
- Two-column layout for fields
- Full-width notes textarea
- Error messages display
- Bootstrap styling
- Submit and reset buttons

#### _delete_form.html.twig
- Danger zone styled card
- Confirmation prompt
- CSRF token included
- Prominent delete button

### 4. **Entity Relationships**

```
Loan (Main Entity)
├── Many-to-One: BookCopy
├── Many-to-One: User (member)
├── One-to-Many: Penalty[] (cascade delete)
└── One-to-Many: Renewal[] (cascade delete)
```

### 5. **Repository Methods**

#### Query Methods

**findActiveLoan()** - Get all active loans
```php
$activeLoan = $loanRepository->findActiveLoan();
```

**findOverdueLoans()** - Get all overdue loans
```php
$overdueLoans = $loanRepository->findOverdueLoans();
```

**findByMember($memberId)** - Get all loans for a member
```php
$memberLoans = $loanRepository->findByMember($userId);
```

**findByBookCopy($bookCopyId)** - Get all loans for a book copy
```php
$copyLoans = $loanRepository->findByBookCopy($copyId);
```

**findLoansWithLateFees()** - Get loans with pending fees
```php
$feeLoans = $loanRepository->findLoansWithLateFees();
```

#### Count Methods

**countTotalLoans()** - Total loan count
**countActiveLoans()** - Active loan count
**countOverdueLoans()** - Overdue loan count

### 6. **Enums**

#### LoanStatus
- `ACTIVE` - Book is currently checked out
- `RETURNED` - Book has been returned
- `OVERDUE` - Due date has passed

#### PaymentStatus
- `UNPAID` - No payment made
- `PAID` - Fully paid
- `PARTIAL` - Partially paid

## Usage Examples

### Creating a Loan Programmatically

```php
$loan = new Loan();
$loan->setCheckoutTime(new DateTime());
$loan->setDueDate((new DateTime())->modify('+14 days'));
$loan->setStatus(LoanStatus::ACTIVE);
$loan->setRenewalCount(0);
$loan->setLateFee(0.0);
$loan->setBookCopy($bookCopy);
$loan->setMember($user);

$entityManager->persist($loan);
$entityManager->flush();
```

### Querying Loans

```php
// Get all active loans
$activeLoan = $loanRepository->findActiveLoan();

// Get loans for a specific member
$memberLoans = $loanRepository->findByMember($memberId);

// Get overdue loans
$overdueLoans = $loanRepository->findOverdueLoans();

// Count statistics
$totalCount = $loanRepository->countTotalLoans();
$activeCount = $loanRepository->countActiveLoans();
$overdueCount = $loanRepository->countOverdueLoans();
```

### Updating a Loan

```php
$loan = $loanRepository->find($id);
$loan->setStatus(LoanStatus::RETURNED);
$loan->setReturnDate(new DateTime());

$entityManager->flush();
```

## Security Features

1. **CSRF Protection** - All forms include CSRF token validation
2. **Entity Validation** - Form fields validated before persistence
3. **Route Parameters** - ID parameter converted to entity object
4. **Delete Confirmation** - JavaScript prompt before deletion
5. **Database Constraints** - Foreign key relationships enforced

## Bootstrap 5 Styling

All templates use Bootstrap 5 classes:
- `.table .table-hover` - Hover effects on tables
- `.badge` - Status indicators
- `.btn-group` - Action button groups
- `.alert` - Flash message displays
- `.card` - Content containers
- `.table-responsive` - Mobile-friendly tables
- `.pagination` - Pagination controls

## Performance Considerations

1. **Pagination** - Prevents loading all loans at once
2. **Query Methods** - Optimized repository queries
3. **Relationships** - Eager loading where needed
4. **Indexes** - Database indexes on foreign keys

## Validation

Form validation includes:
- `NotBlank` - Required fields
- `NotNull` - Enum status
- Entity type validation for relationships
- Custom validation rules in entity

## Flash Messages

### Success Messages
- "Loan created successfully"
- "Loan updated successfully"
- "Loan deleted successfully"

### Error Messages
- Form validation errors displayed
- Database constraint violations

## Testing the Implementation

### 1. Access the Loan Index
Navigate to `/loan/` to see the loan listing

### 2. Create a New Loan
- Click "New Loan" button
- Fill in the form with required fields
- Click "Create Loan"
- Verify redirect to show page

### 3. View Loan Details
- Click view icon in the list or access `/loan/{id}`
- See all loan details including penalties and renewals

### 4. Edit a Loan
- Click edit icon or access `/loan/{id}/edit`
- Modify fields
- Click "Update Loan"
- Verify changes persist

### 5. Delete a Loan
- Click delete button on show page or use form
- Confirm deletion
- Verify redirect to list page

## Database Schema

Automatically created via Doctrine migrations:

```sql
CREATE TABLE loan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_time DATETIME NOT NULL,
    due_date DATE NOT NULL,
    return_date DATETIME NULL,
    status VARCHAR(255) NOT NULL,
    renewal_count INT NOT NULL DEFAULT 0,
    notes LONGTEXT NULL,
    late_fee FLOAT NULL DEFAULT 0,
    book_copy_id INT NOT NULL,
    member_id INT NOT NULL,
    FOREIGN KEY (book_copy_id) REFERENCES book_copy(id),
    FOREIGN KEY (member_id) REFERENCES users(id)
);
```

## Troubleshooting

### Issue: Form shows no book copies or members
**Solution:** Ensure BookCopy and User entities have been created and have data

### Issue: Delete not working
**Solution:** Verify CSRF token is correctly included in the form

### Issue: Pagination not working
**Solution:** Check that page parameter is numeric and within valid range

### Issue: Date fields show wrong format
**Solution:** Adjust date format in templates or form configuration

## Future Enhancements

1. Add search/filter functionality
2. Implement soft deletes
3. Add audit logging
4. Create export to CSV/PDF
5. Add batch operations
6. Implement late fee calculation service
7. Add email notifications
8. Create dashboard with statistics
9. Add batch renewal feature
10. Implement fine/penalty management UI

## Support & Maintenance

For updates to this CRUD:
1. Update form fields when entity changes
2. Update templates for UI improvements
3. Add repository methods for new queries
4. Update validation rules as needed
5. Keep enums synchronized with business logic
