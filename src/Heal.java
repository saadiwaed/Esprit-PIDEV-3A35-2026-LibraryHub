import java.util.Objects;

/**
 * Classe représentant une capacité de soin.
 */
public class Heal implements Ability {

    /**
     * Le montant de points de vie restaurés.
     */
    private int amount;

    /**
     * Constructeur pour créer une nouvelle capacité de soin.
     * @param amount Le montant de soin.
     */
    public Heal(int amount) {
        this.amount = amount;
    }

    /**
     * Utilise le soin sur une créature cible, augmentant sa santé.
     * @param target La créature cible.
     */
    @Override
    public void use(Creature target) {
        target.setHealth(target.getHealth() + amount);
    }

    /**
     * Vérifie si ce soin est égal à un autre objet.
     * @param object L'objet à comparer.
     * @return true si les soins ont le même montant, false sinon.
     */
    @Override
    public boolean equals(Object object) {
        if (this == object) return true;
        if (!(object instanceof Heal heal)) return false;
        return amount == heal.amount;
    }

    /**
     * Génère un code de hachage pour le soin.
     * @return Le code de hachage.
     */
    @Override
    public int hashCode() {
        return Objects.hash(amount);
    }

    /**
     * Retourne une représentation textuelle du soin.
     * @return Une chaîne de caractères décrivant le soin.
     */
    @Override
    public String toString() {
        return "Heal{" +
                "amount=" + amount +
                '}';
    }
}
