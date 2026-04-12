package services;

import model.Renewal;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceRenewal extends AbstractService implements IService<Renewal> {
    public void validate(Renewal renewal) {
        require(renewal != null, "Renewal cannot be null.");
        ValidationUtils.requireDate(renewal.getPreviousDueDate(), "Previous due date");
        ValidationUtils.requireDate(renewal.getNewDueDate(), "New due date");
        require(renewal.getNewDueDate().isAfter(renewal.getPreviousDueDate()), "New due date must be after the current due date.");
        require(renewal.getRenewedAt() != null, "Renewed-at date is required.");
        require(renewal.getRenewalNumber() > 0, "Renewal number must be positive.");
        ValidationUtils.requirePositiveId(renewal.getLoanId(), "Loan ID");
    }

    @Override
    public void ajouter(Renewal renewal) throws SQLException {
        validate(renewal);
        String sql = """
                INSERT INTO renewal (previous_due_date, new_due_date, renewed_at, renewal_number, loan_id)
                VALUES (?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            fillStatement(ps, renewal, false);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    renewal.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(Renewal renewal) throws SQLException {
        validate(renewal);
        ValidationUtils.requirePositiveId(renewal.getId(), "Renewal ID");
        String sql = """
                UPDATE renewal
                SET previous_due_date = ?, new_due_date = ?, renewed_at = ?, renewal_number = ?, loan_id = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, renewal, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM renewal WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<Renewal> afficher() throws SQLException {
        List<Renewal> renewals = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM renewal ORDER BY renewed_at DESC, id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                renewals.add(mapRow(rs));
            }
        }
        return renewals;
    }

    public List<Renewal> findByLoanId(int loanId) throws SQLException {
        List<Renewal> renewals = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM renewal WHERE loan_id = ? ORDER BY renewed_at DESC, id DESC")) {
            ps.setInt(1, loanId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    renewals.add(mapRow(rs));
                }
            }
        }
        return renewals;
    }

    @Override
    public Optional<Renewal> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM renewal WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    private void fillStatement(PreparedStatement ps, Renewal renewal, boolean includeIdAtEnd) throws SQLException {
        ps.setDate(1, toDate(renewal.getPreviousDueDate()));
        ps.setDate(2, toDate(renewal.getNewDueDate()));
        ps.setTimestamp(3, toTimestamp(renewal.getRenewedAt()));
        ps.setInt(4, renewal.getRenewalNumber());
        ps.setInt(5, renewal.getLoanId());
        if (includeIdAtEnd) {
            ps.setInt(6, renewal.getId());
        }
    }

    private Renewal mapRow(ResultSet rs) throws SQLException {
        Renewal renewal = new Renewal();
        renewal.setId(rs.getInt("id"));
        renewal.setPreviousDueDate(toLocalDate(rs.getDate("previous_due_date")));
        renewal.setNewDueDate(toLocalDate(rs.getDate("new_due_date")));
        renewal.setRenewedAt(toLocalDateTime(rs.getTimestamp("renewed_at")));
        renewal.setRenewalNumber(rs.getInt("renewal_number"));
        renewal.setLoanId(rs.getInt("loan_id"));
        if (renewal.getRenewedAt() == null) {
            renewal.setRenewedAt(LocalDateTime.now());
        }
        return renewal;
    }
}
