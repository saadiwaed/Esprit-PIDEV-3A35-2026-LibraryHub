package services;

import model.Member;
import model.MemberStatus;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceMember extends AbstractService implements IService<Member> {
    private static final String DEFAULT_PASSWORD = "desktop_placeholder";

    public void validate(Member member) {
        require(member != null, "Member cannot be null.");
        member.setEmail(ValidationUtils.requireEmail(member.getEmail()));
        member.setFirstName(ValidationUtils.requireText(member.getFirstName(), "First name", 2, 100));
        member.setLastName(ValidationUtils.requireText(member.getLastName(), "Last name", 2, 100));
        member.setPhone(ValidationUtils.optionalText(member.getPhone(), 20));
        member.setAddress(ValidationUtils.optionalText(member.getAddress(), 500));
        require(member.getStatus() != null, "Member status is required.");
        if (member.getCreatedAt() == null) {
            member.setCreatedAt(LocalDateTime.now());
        }
    }

    @Override
    public void ajouter(Member member) throws SQLException {
        validate(member);
        String sql = """
                INSERT INTO `user` (email, password, first_name, last_name, phone, address, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, member.getEmail());
            ps.setString(2, DEFAULT_PASSWORD);
            ps.setString(3, member.getFirstName());
            ps.setString(4, member.getLastName());
            ps.setString(5, member.getPhone());
            ps.setString(6, member.getAddress());
            ps.setString(7, member.getStatus().name());
            ps.setTimestamp(8, toTimestamp(member.getCreatedAt()));
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    member.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(Member member) throws SQLException {
        validate(member);
        ValidationUtils.requirePositiveId(member.getId(), "Member ID");
        String sql = """
                UPDATE `user`
                SET email = ?, first_name = ?, last_name = ?, phone = ?, address = ?, status = ?, created_at = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            ps.setString(1, member.getEmail());
            ps.setString(2, member.getFirstName());
            ps.setString(3, member.getLastName());
            ps.setString(4, member.getPhone());
            ps.setString(5, member.getAddress());
            ps.setString(6, member.getStatus().name());
            ps.setTimestamp(7, toTimestamp(member.getCreatedAt()));
            ps.setInt(8, member.getId());
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        String sql = "DELETE FROM `user` WHERE id = ?";
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<Member> afficher() throws SQLException {
        List<Member> members = new ArrayList<>();
        String sql = """
                SELECT id, email, first_name, last_name, phone, address, status, created_at
                FROM `user`
                ORDER BY id DESC
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                members.add(mapRow(rs));
            }
        }
        return members;
    }

    @Override
    public Optional<Member> getById(int id) throws SQLException {
        String sql = """
                SELECT id, email, first_name, last_name, phone, address, status, created_at
                FROM `user`
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    private Member mapRow(ResultSet rs) throws SQLException {
        Member member = new Member();
        member.setId(rs.getInt("id"));
        member.setEmail(rs.getString("email"));
        member.setFirstName(rs.getString("first_name"));
        member.setLastName(rs.getString("last_name"));
        member.setPhone(rs.getString("phone"));
        member.setAddress(rs.getString("address"));
        member.setStatus(MemberStatus.valueOf(rs.getString("status").toUpperCase()));
        member.setCreatedAt(toLocalDateTime(rs.getTimestamp("created_at")));
        return member;
    }
}
