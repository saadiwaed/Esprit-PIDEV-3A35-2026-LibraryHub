import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

/**
 * Classe représentant un sanctuaire contenant des créatures.
 */
public class Sanctuary {

    /**
     * Liste des créatures présentes dans le sanctuaire.
     */
    private List<Creature> creatures;

    /**
     * Affiche les noms des créatures triés par longueur de nom.
     * (1pt)
     */
    public void displayCreaturesNamesSortedByLength() {
        creatures.stream()
                .map(c -> c.getName())
                .sorted((s1, s2) -> s1.length() - s2.length())
                .forEach(s -> System.out.println(s));

//        creatures.stream()
//                .map(Creature::getName)
//                .sorted(Comparator.comparingInt(String::length))
//                .forEach(System.out::println);
    }

    /**
     * Regroupe les créatures par espèce.
     * @return Une map associant chaque espèce à sa liste de créatures.
     * (1pt)
     */
    public Map<String, List<Creature>> groupCreaturesBySpecies() {
        return creatures.stream()
                .distinct()
                .collect(Collectors.groupingBy(c -> c.getSpecies()));

//        return creatures.stream()
//                .collect(Collectors.groupingBy(Creature::getSpecies));
    }

    /**
     * Affiche le nombre de créatures pour chaque espèce.
     * (2pts)
     */
    public void displayNumberOfCreaturesBySpecies() {
        groupCreaturesBySpecies().entrySet().stream()
                .map(e -> e.getKey() + ": " + e.getValue().size())
                .forEach(s -> System.out.println(s));


//        groupCreaturesBySpecies(species).entrySet().stream()
//                .map(e -> e.getKey() + ": " + e.getValue().size())
//                .forEach(System.out::println);
    }

    /**
     * Retourne le nombre de prédateurs d'une créature donnée.
     * @param creature La créature cible.
     * @return Le nombre de prédateurs.
     * (1pt)
     */
    public Long returnNumberOfPredators(Creature creature) {
        return creatures.stream()
                .filter(c -> c.getPreyList().contains(creature))
                .count();
    }

}
