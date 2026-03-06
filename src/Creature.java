import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * Classe représentant une créature dans le sanctuaire.
 */
public class Creature {

    /**
     * Ensemble des capacités de la créature.
     * (1,5 pt)
     */
    private final Set<Ability> abilities;
    /**
     * Liste des proies de la créature.
     */
    private final List<Creature> preyList;
    /**
     * Points de vie de la créature.
     */
    private int health;
    /**
     * Identifiant unique de la créature.
     */
    private int id;
    /**
     * Nom de la créature.
     */
    private String name;
    /**
     * Espèce de la créature.
     */
    private String species;

    /**
     * Constructeur pour créer une nouvelle créature.
     * @param name Le nom de la créature.
     * @param id L'identifiant.
     * @param species L'espèce.
     * (1.25pt)
     */
    public Creature(String name, int id, String species) {
        this.name = name;
        this.id = id;
        this.species = species;
        this.health = 100;
        this.abilities = new HashSet<>();
        this.preyList = new ArrayList<>();
    }

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public String getSpecies() {
        return species;
    }

    public void setSpecies(String species) {
        this.species = species;
    }

    public Set<Ability> getAbilities() {
        return abilities;
    }

    /**
     * Ajoute une capacité à la créature.
     * @param ability La capacité à ajouter.
     * (O.75pt)
     */
    public void addAbility(Ability ability) {
        this.abilities.add(ability);
    }

    /**
     * Supprime une capacité de la créature.
     * @param ability La capacité à supprimer.
     * (O.75pt)
     */
    public void removeAbility(Ability ability) {
        this.abilities.remove(ability);
    }

    public List<Creature> getPreyList() {
        return preyList;
    }

    public int getHealth() {
        return health;
    }

    public void setHealth(int health) {
        this.health = health;
    }

    /**
     * Vérifie si cette créature est un prédateur d'une autre.
     * @param other L'autre créature.
     * @return true si elle est un prédateur, false sinon.
     * (O.75pt)
     */
    public boolean isPredatorOf(Creature other) {
        return this.preyList.contains(other);
    }

    /**
     * Ajoute une proie à la liste des proies de la créature.
     * @param creature La créature proie.
     * @throws InvalidInteractionException Si la proie est de la même espèce.
     * (1,5pts)
     */
    public void addPrey(Creature creature) throws InvalidInteractionException {
        if (this.species.equals(creature.species)) throw new InvalidInteractionException("Can't hunt creatures of the same species.");

        this.preyList.add(creature);
    }

    /**
     * Utilise une capacité sur une autre créature.
     * @param ability La capacité à utiliser.
     * @param creature La créature cible.
     * (1,5pts)
     */
    public void useAbility(Ability ability, Creature creature) {
        ability.use(creature);
        if (creature.health > 100) creature.health = 100;
        if (creature.health <= 0) System.out.println(creature.name + " is now dead.");
    }

    @Override
    public String toString() {
        return "Creature{" + "name='" + name + '\'' + ", id=" + id + ", species='" + species + '\'' + ", abilities=" + abilities + ", foodSources=" + preyList + '}';
    }
}
