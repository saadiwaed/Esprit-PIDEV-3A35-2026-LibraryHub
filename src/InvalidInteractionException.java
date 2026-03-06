/**
 * Exception levée lorsqu'une interaction invalide entre créatures est tentée.
 */
public class InvalidInteractionException extends Exception {
    /**
     * Constructeur avec un message d'erreur.
     * @param msg Le message décrivant l'erreur.
     */
    public InvalidInteractionException(String msg) {
        super(msg);
    }
}
