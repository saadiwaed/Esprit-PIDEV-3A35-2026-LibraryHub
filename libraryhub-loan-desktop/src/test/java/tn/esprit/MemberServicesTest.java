package tn.esprit;

import model.Member;
import model.MemberStatus;
import org.junit.jupiter.api.Test;
import services.ServiceMember;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;
import static org.junit.jupiter.api.Assertions.assertThrows;

class MemberServicesTest {
    @Test
    void validatesAMember() {
        Member member = new Member();
        member.setEmail("member@libraryhub.tn");
        member.setFirstName("Ali");
        member.setLastName("Trabelsi");
        member.setPhone("+21612345678");
        member.setStatus(MemberStatus.ACTIVE);

        assertDoesNotThrow(() -> new ServiceMember().validate(member));
    }

    @Test
    void rejectsInvalidEmail() {
        Member member = new Member();
        member.setEmail("bad");
        member.setFirstName("Ali");
        member.setLastName("Trabelsi");
        member.setStatus(MemberStatus.ACTIVE);

        assertThrows(IllegalArgumentException.class, () -> new ServiceMember().validate(member));
    }
}
