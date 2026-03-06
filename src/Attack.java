import java.util.Objects;

/**
 * Classe représentant une capacité d'attaque.
 * 0.25 pt
 */
public class Attack implements Ability {
    /**
     * Les points de dégâts infligés par l'attaque.
     * 0.25 pt
     */
    private int damage;

    /**
     * Constructeur pour créer une nouvelle attaque.
     * @param damage Les points de dégâts.
     * 0.5 pt
     */
    public Attack(int damage) {
        this.damage = damage;
    }

    /**
     * Utilise l'attaque sur une créature cible, réduisant sa santé.
     * @param target La créature cible.
     * 1 pt
     */
    @Override
    public void use(Creature target) {
        target.setHealth(target.getHealth() - damage);
    }

    /**
     * Vérifie si cette attaque est égale à un autre objet.
     * @param object L'objet à comparer.
     * @return true si les attaques ont les mêmes dégâts, false sinon.
     * 0.5 pt
     */
    @Override
    public boolean equals(Object object) {
        if (this == object) return true;
        if (!(object instanceof Attack attack)) return false;
        return damage == attack.damage;
    }

    /**
     * Génère un code de hachage pour l'attaque.
     * @return Le code de hachage.
     * 1 pt
     */
    @Override
    public int hashCode() {
        return Objects.hash(damage);
    }

    /**
     * Retourne une représentation textuelle de l'attaque.
     * @return Une chaîne de caractères décrivant l'attaque.
     * 0.25 pt
     */
    @Override
    public String toString() {
        return "Attack{" +
                "damage=" + damage +
                '}';
    }
}
