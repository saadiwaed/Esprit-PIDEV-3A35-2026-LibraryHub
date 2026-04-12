package services;

import model.BookCopy;
import model.Loan;
import model.LoanRequest;
import model.LoanStatus;
import model.RequestStatus;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceLoanRequest extends AbstractService implements IService<LoanRequest> {
    private final ServiceMember serviceMember = new ServiceMember();
    private final ServiceBook serviceBook = new ServiceBook();
    private final ServiceBookCopy serviceBookCopy = new ServiceBookCopy();
    private final ServiceLoan serviceLoan = new ServiceLoan();

    public void validate(LoanRequest request) {
        require(request != null, "Loan request cannot be null.");
        ValidationUtils.requirePositiveId(request.getMemberId(), "Member ID");
        ValidationUtils.requirePositiveId(request.getBookId(), "Book ID");
        ValidationUtils.requireDate(request.getDesiredLoanDate(), "Desired loan date");
        ValidationUtils.requireDate(request.getDesiredReturnDate(), "Desired return date");
        require(request.getDesiredReturnDate().isAfter(request.getDesiredLoanDate()), "Desired return date must be after desired loan date.");
        if (request.getId() == null) {
            require(!request.getDesiredLoanDate().isBefore(LocalDate.now()), "Desired loan date must be today or later.");
        }
        request.setPhoneNumber(ValidationUtils.normalizeTunisianPhone(request.getPhoneNumber(), true));
        request.setNotes(ValidationUtils.optionalText(request.getNotes(), 1000));
        if (request.getRequestedAt() == null) {
            request.setRequestedAt(LocalDateTime.now());
        }
        if (request.getStatus() == null) {
            request.setStatus(RequestStatus.PENDING);
        }
    }

    @Override
    public void ajouter(LoanRequest request) throws SQLException {
        validate(request);
        ensureReferencesExist(request);
        String sql = """
                INSERT INTO loan_request (member_id, book_id, desired_loan_date, desired_return_date, requested_at, status, phone_number, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            fillStatement(ps, request, false);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    request.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(LoanRequest request) throws SQLException {
        validate(request);
        ValidationUtils.requirePositiveId(request.getId(), "Loan request ID");
        ensureReferencesExist(request);
        String sql = """
                UPDATE loan_request
                SET member_id = ?, book_id = ?, desired_loan_date = ?, desired_return_date = ?, requested_at = ?, status = ?, phone_number = ?, notes = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, request, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM loan_request WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<LoanRequest> afficher() throws SQLException {
        List<LoanRequest> requests = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM loan_request ORDER BY requested_at DESC, id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                requests.add(mapRow(rs));
            }
        }
        return requests;
    }

    @Override
    public Optional<LoanRequest> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM loan_request WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    public Loan approveRequest(int requestId) throws SQLException {
        LoanRequest request = getById(requestId).orElseThrow(() -> new IllegalArgumentException("Loan request not found."));
        if (request.getStatus() != RequestStatus.PENDING) {
            throw new IllegalArgumentException("Only pending requests can be approved.");
        }

        BookCopy bookCopy = serviceBookCopy.getById(request.getBookId()).orElseGet(BookCopy::new);
        if (bookCopy.getId() == null) {
            serviceBookCopy.ajouter(bookCopy);
        }

        LocalDateTime now = LocalDateTime.now();
        LocalDateTime checkout = now;
        if (request.getDesiredLoanDate() != null && !request.getDesiredLoanDate().isAfter(LocalDate.now())) {
            checkout = request.getDesiredLoanDate().atTime(now.getHour(), now.getMinute(), now.getSecond());
        }

        Loan loan = new Loan();
        loan.setMemberId(request.getMemberId());
        loan.setBookCopyId(bookCopy.getId());
        loan.setPhoneNumber(request.getPhoneNumber());
        loan.setCheckoutTime(checkout);
        loan.setDueDate(request.getDesiredReturnDate() != null ? request.getDesiredReturnDate() : checkout.toLocalDate().plusDays(DEFAULT_RENEWAL_DAYS));
        loan.setStatus(LoanStatus.ACTIVE);
        loan.setRenewalCount(0);
        serviceLoan.ajouter(loan);

        request.setStatus(RequestStatus.APPROVED);
        modifier(request);

        return loan;
    }

    public void rejectRequest(int requestId, String reason) throws SQLException {
        LoanRequest request = getById(requestId).orElseThrow(() -> new IllegalArgumentException("Loan request not found."));
        if (request.getStatus() != RequestStatus.PENDING) {
            throw new IllegalArgumentException("Only pending requests can be rejected.");
        }
        if (reason != null && !reason.isBlank()) {
            String existing = request.getNotes() == null ? "" : request.getNotes().trim();
            request.setNotes((existing.isBlank() ? "" : existing + System.lineSeparator()) + "Motif du refus: " + reason.trim());
        }
        request.setStatus(RequestStatus.REJECTED);
        modifier(request);
    }

    private void ensureReferencesExist(LoanRequest request) throws SQLException {
        if (serviceMember.getById(request.getMemberId()).isEmpty()) {
            throw new IllegalArgumentException("Selected member does not exist.");
        }
        if (serviceBook.getById(request.getBookId()).isEmpty() && serviceBookCopy.getById(request.getBookId()).isEmpty()) {
            throw new IllegalArgumentException("Book or book copy not found for the entered ID.");
        }
    }

    private void fillStatement(PreparedStatement ps, LoanRequest request, boolean includeIdAtEnd) throws SQLException {
        ps.setInt(1, request.getMemberId());
        ps.setInt(2, request.getBookId());
        ps.setDate(3, toDate(request.getDesiredLoanDate()));
        ps.setDate(4, toDate(request.getDesiredReturnDate()));
        ps.setTimestamp(5, toTimestamp(request.getRequestedAt()));
        ps.setString(6, request.getStatus().getDbValue());
        ps.setString(7, request.getPhoneNumber());
        ps.setString(8, request.getNotes());
        if (includeIdAtEnd) {
            ps.setInt(9, request.getId());
        }
    }

    private LoanRequest mapRow(ResultSet rs) throws SQLException {
        LoanRequest request = new LoanRequest();
        request.setId(rs.getInt("id"));
        request.setMemberId(rs.getInt("member_id"));
        request.setBookId(rs.getInt("book_id"));
        request.setDesiredLoanDate(toLocalDate(rs.getDate("desired_loan_date")));
        request.setDesiredReturnDate(toLocalDate(rs.getDate("desired_return_date")));
        request.setRequestedAt(toLocalDateTime(rs.getTimestamp("requested_at")));
        request.setStatus(RequestStatus.fromDbValue(rs.getString("status")));
        request.setPhoneNumber(rs.getString("phone_number"));
        request.setNotes(rs.getString("notes"));
        return request;
    }
}
