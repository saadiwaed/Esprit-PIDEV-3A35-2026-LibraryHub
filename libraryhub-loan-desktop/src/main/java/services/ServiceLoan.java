package services;

import model.Loan;
import model.LoanStatus;
import model.PaymentStatus;
import model.Penalty;
import model.Renewal;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceLoan extends AbstractService implements IService<Loan> {
    private final ServiceMember serviceMember = new ServiceMember();
    private final ServiceBookCopy serviceBookCopy = new ServiceBookCopy();
    private final ServiceRenewal serviceRenewal = new ServiceRenewal();
    private final ServicePenalty servicePenalty = new ServicePenalty();

    public void validate(Loan loan) {
        require(loan != null, "Loan cannot be null.");
        require(loan.getCheckoutTime() != null, "Checkout time is required.");
        require(loan.getDueDate() != null, "Due date is required.");
        require(!loan.getDueDate().isBefore(loan.getCheckoutTime().toLocalDate()), "Due date must be on or after the checkout day.");
        require(loan.getRenewalCount() >= 0, "Renewal count cannot be negative.");
        require(loan.getStatus() != null, "Loan status is required.");
        ValidationUtils.requirePositiveId(loan.getBookCopyId(), "Book copy ID");
        ValidationUtils.requirePositiveId(loan.getMemberId(), "Member ID");
        loan.setPhoneNumber(ValidationUtils.normalizeTunisianPhone(loan.getPhoneNumber(), false));
        loan.setNotes(ValidationUtils.optionalText(loan.getNotes(), 2000));

        if (loan.getReturnDate() != null && loan.getReturnDate().isBefore(loan.getCheckoutTime())) {
            throw new IllegalArgumentException("Return date must be after checkout time.");
        }
        if (loan.getStatus() != LoanStatus.RETURNED && loan.getReturnDate() != null) {
            throw new IllegalArgumentException("Return date must stay empty until the loan is marked as returned.");
        }
        if (loan.getStatus() == LoanStatus.RETURNED && loan.getReturnDate() == null) {
            throw new IllegalArgumentException("Return date is required when the status is RETURNED.");
        }
        if (loan.getId() == null && loan.getReturnDate() == null && loan.getDueDate().isBefore(LocalDate.now())) {
            throw new IllegalArgumentException("A new loan cannot start with a due date in the past.");
        }
    }

    @Override
    public void ajouter(Loan loan) throws SQLException {
        if (loan.getCheckoutTime() == null) {
            loan.setCheckoutTime(LocalDateTime.now());
        }
        if (loan.getDueDate() == null) {
            loan.setDueDate(calculateDueDate(loan.getCheckoutTime()));
        }
        loan.refreshStatusFromDates();
        validate(loan);
        ensureReferencesExist(loan);

        String sql = """
                INSERT INTO loan (checkout_time, due_date, return_date, status, renewal_count, notes, book_copy_id, member_id, phone_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            fillStatement(ps, loan, false);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    loan.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(Loan loan) throws SQLException {
        validate(loan);
        ValidationUtils.requirePositiveId(loan.getId(), "Loan ID");
        ensureReferencesExist(loan);

        Optional<Loan> stored = getById(loan.getId());
        if (stored.isPresent() && stored.get().getReturnDate() != null && !stored.get().getDueDate().equals(loan.getDueDate())) {
            throw new IllegalArgumentException("Due date cannot be changed after a return date has been recorded.");
        }

        String sql = """
                UPDATE loan
                SET checkout_time = ?, due_date = ?, return_date = ?, status = ?, renewal_count = ?, notes = ?, book_copy_id = ?, member_id = ?, phone_number = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, loan, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM loan WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<Loan> afficher() throws SQLException {
        List<Loan> loans = new ArrayList<>();
        String sql = """
                SELECT * FROM loan
                ORDER BY CASE status
                    WHEN 'overdue' THEN 0
                    WHEN 'active' THEN 1
                    WHEN 'returned' THEN 2
                    ELSE 3
                END, due_date ASC, id DESC
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                loans.add(mapRow(rs));
            }
        }
        return loans;
    }

    public List<Loan> findByMemberId(int memberId) throws SQLException {
        List<Loan> loans = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM loan WHERE member_id = ? ORDER BY checkout_time DESC")) {
            ps.setInt(1, memberId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    loans.add(mapRow(rs));
                }
            }
        }
        return loans;
    }

    @Override
    public Optional<Loan> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM loan WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    public LocalDate calculateDueDate(LocalDateTime checkoutTime) {
        return checkoutTime.toLocalDate().plusDays(DEFAULT_LOAN_DAYS);
    }

    public void returnLoan(int loanId) throws SQLException {
        Loan loan = getById(loanId).orElseThrow(() -> new IllegalArgumentException("Loan not found."));
        if (loan.getReturnDate() != null) {
            throw new IllegalArgumentException("This loan is already marked as returned.");
        }
        loan.setReturnDate(LocalDateTime.now());
        loan.setStatus(LoanStatus.RETURNED);
        modifier(loan);
    }

    public Renewal renewLoan(int loanId, LocalDate newDueDate) throws SQLException {
        Loan loan = getById(loanId).orElseThrow(() -> new IllegalArgumentException("Loan not found."));
        if (!loan.canBeRenewed()) {
            throw new IllegalArgumentException("Only active or overdue open loans can be renewed.");
        }
        if (loan.maxRenewalsReached(MAX_RENEWALS)) {
            throw new IllegalArgumentException("Maximum number of renewals reached.");
        }
        LocalDate targetDueDate = newDueDate == null ? loan.getDueDate().plusDays(DEFAULT_RENEWAL_DAYS) : newDueDate;
        require(targetDueDate.isAfter(loan.getDueDate()), "The new due date must be after the current one.");

        Renewal renewal = new Renewal();
        renewal.setLoanId(loan.getId());
        renewal.setPreviousDueDate(loan.getDueDate());
        renewal.setNewDueDate(targetDueDate);
        renewal.setRenewedAt(LocalDateTime.now());
        renewal.setRenewalNumber(loan.getRenewalCount() + 1);
        serviceRenewal.ajouter(renewal);

        loan.setDueDate(targetDueDate);
        loan.setRenewalCount(loan.getRenewalCount() + 1);
        loan.refreshStatusFromDates();
        modifier(loan);

        return renewal;
    }

    public int refreshOverdueStatuses() throws SQLException {
        int updated = 0;
        for (Loan loan : afficher()) {
            if (loan.getReturnDate() == null && loan.getStatus() == LoanStatus.ACTIVE && loan.getDueDate().isBefore(LocalDate.now())) {
                loan.setStatus(LoanStatus.OVERDUE);
                modifier(loan);
                updated++;
            }
        }
        return updated;
    }

    public Penalty createLatePenalty(int loanId, BigDecimal dailyRate, String notes) throws SQLException {
        Loan loan = getById(loanId).orElseThrow(() -> new IllegalArgumentException("Loan not found."));
        loan.refreshStatusFromDates();
        if (loan.getStatus() != LoanStatus.OVERDUE) {
            throw new IllegalArgumentException("Only overdue loans can receive a late penalty.");
        }
        if (servicePenalty.findActiveLatePenaltyForLoan(loanId).isPresent()) {
            throw new IllegalArgumentException("An active late penalty already exists for this loan.");
        }
        int daysLate = loan.getDaysLate();
        if (daysLate <= 0) {
            throw new IllegalArgumentException("This loan is not late.");
        }

        Penalty penalty = new Penalty();
        penalty.setLoanId(loanId);
        penalty.setDailyRate(dailyRate == null ? new BigDecimal("0.50") : dailyRate);
        penalty.setLateDays(daysLate);
        penalty.setAmount(penalty.getDailyRate().multiply(BigDecimal.valueOf(daysLate)).setScale(2, RoundingMode.HALF_UP));
        penalty.setReason(Penalty.DAILY_LATE_REASON_PREFIX + " - Retard de " + daysLate + " jours");
        penalty.setIssueDate(LocalDate.now());
        penalty.setWaived(false);
        penalty.setStatus(PaymentStatus.UNPAID);
        penalty.setNotes(notes);
        servicePenalty.ajouter(penalty);
        return penalty;
    }

    public int generateOrUpdateOverduePenalties() throws SQLException {
        int updated = 0;
        refreshOverdueStatuses();
        for (Loan loan : afficher()) {
            if (loan.getStatus() != LoanStatus.OVERDUE || loan.getReturnDate() != null) {
                continue;
            }
            int daysLate = loan.getDaysLate();
            if (daysLate <= 0) {
                continue;
            }
            BigDecimal rate = new BigDecimal("0.50");
            BigDecimal amount = rate.multiply(BigDecimal.valueOf(daysLate)).setScale(2, RoundingMode.HALF_UP);
            Optional<Penalty> existing = servicePenalty.findActiveLatePenaltyForLoan(loan.getId());
            if (existing.isPresent()) {
                Penalty penalty = existing.get();
                penalty.setDailyRate(rate);
                penalty.setLateDays(daysLate);
                penalty.setAmount(amount);
                penalty.setReason(Penalty.DAILY_LATE_REASON_PREFIX + " - Retard de " + daysLate + " jours");
                penalty.setIssueDate(LocalDate.now());
                penalty.setStatus(PaymentStatus.UNPAID);
                penalty.setWaived(false);
                servicePenalty.modifier(penalty);
            } else {
                createLatePenalty(loan.getId(), rate, "AUTO_OVERDUE_DAILY");
            }
            updated++;
        }
        return updated;
    }

    private void fillStatement(PreparedStatement ps, Loan loan, boolean includeIdAtEnd) throws SQLException {
        ps.setTimestamp(1, toTimestamp(loan.getCheckoutTime()));
        ps.setDate(2, toDate(loan.getDueDate()));
        ps.setTimestamp(3, toTimestamp(loan.getReturnDate()));
        ps.setString(4, loan.getStatus().getDbValue());
        ps.setInt(5, loan.getRenewalCount());
        ps.setString(6, loan.getNotes());
        ps.setInt(7, loan.getBookCopyId());
        ps.setInt(8, loan.getMemberId());
        ps.setString(9, loan.getPhoneNumber());
        if (includeIdAtEnd) {
            ps.setInt(10, loan.getId());
        }
    }

    private void ensureReferencesExist(Loan loan) throws SQLException {
        if (serviceBookCopy.getById(loan.getBookCopyId()).isEmpty()) {
            throw new IllegalArgumentException("The selected book copy does not exist.");
        }
        if (serviceMember.getById(loan.getMemberId()).isEmpty()) {
            throw new IllegalArgumentException("The selected member does not exist.");
        }
    }

    private Loan mapRow(ResultSet rs) throws SQLException {
        Loan loan = new Loan();
        loan.setId(rs.getInt("id"));
        loan.setCheckoutTime(toLocalDateTime(rs.getTimestamp("checkout_time")));
        loan.setDueDate(toLocalDate(rs.getDate("due_date")));
        loan.setReturnDate(toLocalDateTime(rs.getTimestamp("return_date")));
        loan.setStatus(LoanStatus.fromDbValue(rs.getString("status")));
        loan.setRenewalCount(rs.getInt("renewal_count"));
        loan.setNotes(rs.getString("notes"));
        loan.setBookCopyId(rs.getInt("book_copy_id"));
        loan.setMemberId(rs.getInt("member_id"));
        loan.setPhoneNumber(rs.getString("phone_number"));
        return loan;
    }
}
