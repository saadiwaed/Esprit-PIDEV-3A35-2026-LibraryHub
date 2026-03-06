/**
 * Interface représentant une capacité qu'une créature peut posséder.
 * (1pt)
 */
public interface Ability {

    /**
     * Utilise la capacité sur une créature cible.
     * @param target La créature sur laquelle la capacité est utilisée.
     */
    void use(Creature target);
}
