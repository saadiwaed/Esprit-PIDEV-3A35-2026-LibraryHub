package services;

import model.PaymentStatus;
import model.Penalty;

import java.math.BigDecimal;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServicePenalty extends AbstractService implements IService<Penalty> {
    public void validate(Penalty penalty) {
        require(penalty != null, "Penalty cannot be null.");
        require(penalty.getAmount() != null && penalty.getAmount().compareTo(BigDecimal.ZERO) > 0, "Penalty amount must be strictly positive.");
        require(penalty.getDailyRate() != null && penalty.getDailyRate().compareTo(BigDecimal.ZERO) > 0, "Daily rate must be strictly positive.");
        require(penalty.getLateDays() >= 0, "Late days cannot be negative.");
        penalty.setReason(ValidationUtils.requireText(penalty.getReason(), "Reason", 2, 255));
        require(!Penalty.REASON_OTHER.equalsIgnoreCase(penalty.getReason()), "Please provide a custom reason instead of the literal 'other'.");
        require(penalty.getIssueDate() != null, "Issue date is required.");
        require(!penalty.getIssueDate().isAfter(LocalDate.now()), "Issue date cannot be in the future.");
        require(penalty.getStatus() != null, "Payment status is required.");
        ValidationUtils.requirePositiveId(penalty.getLoanId(), "Loan ID");
        penalty.setNotes(ValidationUtils.optionalText(penalty.getNotes(), 1000));
    }

    @Override
    public void ajouter(Penalty penalty) throws SQLException {
        validate(penalty);
        String sql = """
                INSERT INTO penalty (amount, daily_rate, late_days, reason, issue_date, notes, waived, status, loan_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            fillStatement(ps, penalty, false);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    penalty.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(Penalty penalty) throws SQLException {
        validate(penalty);
        ValidationUtils.requirePositiveId(penalty.getId(), "Penalty ID");
        String sql = """
                UPDATE penalty
                SET amount = ?, daily_rate = ?, late_days = ?, reason = ?, issue_date = ?, notes = ?, waived = ?, status = ?, loan_id = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, penalty, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM penalty WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<Penalty> afficher() throws SQLException {
        List<Penalty> penalties = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM penalty ORDER BY issue_date DESC, id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                penalties.add(mapRow(rs));
            }
        }
        return penalties;
    }

    public List<Penalty> findByLoanId(int loanId) throws SQLException {
        List<Penalty> penalties = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM penalty WHERE loan_id = ? ORDER BY issue_date DESC, id DESC")) {
            ps.setInt(1, loanId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    penalties.add(mapRow(rs));
                }
            }
        }
        return penalties;
    }

    public Optional<Penalty> findActiveLatePenaltyForLoan(int loanId) throws SQLException {
        String sql = """
                SELECT * FROM penalty
                WHERE loan_id = ?
                  AND waived = 0
                  AND status = ?
                  AND (reason = ? OR reason LIKE ?)
                ORDER BY id DESC
                LIMIT 1
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            ps.setInt(1, loanId);
            ps.setString(2, PaymentStatus.UNPAID.getDbValue());
            ps.setString(3, Penalty.REASON_LATE_RETURN);
            ps.setString(4, Penalty.DAILY_LATE_REASON_PREFIX + "%");
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    @Override
    public Optional<Penalty> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM penalty WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    private void fillStatement(PreparedStatement ps, Penalty penalty, boolean includeIdAtEnd) throws SQLException {
        ps.setBigDecimal(1, penalty.getAmount());
        ps.setBigDecimal(2, penalty.getDailyRate());
        ps.setInt(3, penalty.getLateDays());
        ps.setString(4, penalty.getReason());
        ps.setDate(5, toDate(penalty.getIssueDate()));
        ps.setString(6, penalty.getNotes());
        ps.setBoolean(7, penalty.isWaived());
        ps.setString(8, penalty.getStatus().getDbValue());
        ps.setInt(9, penalty.getLoanId());
        if (includeIdAtEnd) {
            ps.setInt(10, penalty.getId());
        }
    }

    private Penalty mapRow(ResultSet rs) throws SQLException {
        Penalty penalty = new Penalty();
        penalty.setId(rs.getInt("id"));
        penalty.setAmount(rs.getBigDecimal("amount"));
        penalty.setDailyRate(rs.getBigDecimal("daily_rate"));
        penalty.setLateDays(rs.getInt("late_days"));
        penalty.setReason(rs.getString("reason"));
        penalty.setIssueDate(toLocalDate(rs.getDate("issue_date")));
        penalty.setNotes(rs.getString("notes"));
        penalty.setWaived(rs.getBoolean("waived"));
        penalty.setStatus(PaymentStatus.fromDbValue(rs.getString("status")));
        penalty.setLoanId(rs.getInt("loan_id"));
        return penalty;
    }
}
