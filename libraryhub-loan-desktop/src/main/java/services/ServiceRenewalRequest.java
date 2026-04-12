package services;

import model.Loan;
import model.Renewal;
import model.RenewalRequest;
import model.RequestStatus;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceRenewalRequest extends AbstractService implements IService<RenewalRequest> {
    private final ServiceLoan serviceLoan = new ServiceLoan();
    private final ServiceMember serviceMember = new ServiceMember();

    public void validate(RenewalRequest request) {
        require(request != null, "Renewal request cannot be null.");
        ValidationUtils.requirePositiveId(request.getLoanId(), "Loan ID");
        ValidationUtils.requirePositiveId(request.getMemberId(), "Member ID");
        if (request.getRequestedAt() == null) {
            request.setRequestedAt(LocalDateTime.now());
        }
        if (request.getStatus() == null) {
            request.setStatus(RequestStatus.PENDING);
        }
        request.setNotes(ValidationUtils.optionalText(request.getNotes(), 1000));
    }

    @Override
    public void ajouter(RenewalRequest request) throws SQLException {
        validate(request);
        ensureReferencesExist(request);
        String sql = """
                INSERT INTO renewal_request (loan_id, member_id, requested_at, status, notes)
                VALUES (?, ?, ?, ?, ?)
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
    public void modifier(RenewalRequest request) throws SQLException {
        validate(request);
        ValidationUtils.requirePositiveId(request.getId(), "Renewal request ID");
        ensureReferencesExist(request);
        String sql = """
                UPDATE renewal_request
                SET loan_id = ?, member_id = ?, requested_at = ?, status = ?, notes = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, request, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM renewal_request WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<RenewalRequest> afficher() throws SQLException {
        List<RenewalRequest> requests = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM renewal_request ORDER BY requested_at DESC, id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                requests.add(mapRow(rs));
            }
        }
        return requests;
    }

    @Override
    public Optional<RenewalRequest> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM renewal_request WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    public Renewal approveRequest(int requestId) throws SQLException {
        RenewalRequest request = getById(requestId).orElseThrow(() -> new IllegalArgumentException("Renewal request not found."));
        if (request.getStatus() != RequestStatus.PENDING) {
            throw new IllegalArgumentException("Only pending requests can be approved.");
        }
        Loan loan = serviceLoan.getById(request.getLoanId()).orElseThrow(() -> new IllegalArgumentException("Loan not found."));
        if (loan.getMemberId() != null && !loan.getMemberId().equals(request.getMemberId())) {
            throw new IllegalArgumentException("The selected member does not match the loan member.");
        }
        Renewal renewal = serviceLoan.renewLoan(request.getLoanId(), loan.getDueDate().plusDays(DEFAULT_RENEWAL_DAYS));
        request.setStatus(RequestStatus.APPROVED);
        modifier(request);
        return renewal;
    }

    public void rejectRequest(int requestId, String reason) throws SQLException {
        RenewalRequest request = getById(requestId).orElseThrow(() -> new IllegalArgumentException("Renewal request not found."));
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

    private void ensureReferencesExist(RenewalRequest request) throws SQLException {
        if (serviceLoan.getById(request.getLoanId()).isEmpty()) {
            throw new IllegalArgumentException("Selected loan does not exist.");
        }
        if (serviceMember.getById(request.getMemberId()).isEmpty()) {
            throw new IllegalArgumentException("Selected member does not exist.");
        }
    }

    private void fillStatement(PreparedStatement ps, RenewalRequest request, boolean includeIdAtEnd) throws SQLException {
        ps.setInt(1, request.getLoanId());
        ps.setInt(2, request.getMemberId());
        ps.setTimestamp(3, toTimestamp(request.getRequestedAt()));
        ps.setString(4, request.getStatus().getDbValue());
        ps.setString(5, request.getNotes());
        if (includeIdAtEnd) {
            ps.setInt(6, request.getId());
        }
    }

    private RenewalRequest mapRow(ResultSet rs) throws SQLException {
        RenewalRequest request = new RenewalRequest();
        request.setId(rs.getInt("id"));
        request.setLoanId(rs.getInt("loan_id"));
        request.setMemberId(rs.getInt("member_id"));
        request.setRequestedAt(toLocalDateTime(rs.getTimestamp("requested_at")));
        request.setStatus(RequestStatus.fromDbValue(rs.getString("status")));
        request.setNotes(rs.getString("notes"));
        return request;
    }
}
