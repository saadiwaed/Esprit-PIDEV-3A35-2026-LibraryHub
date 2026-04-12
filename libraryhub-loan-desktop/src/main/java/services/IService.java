package services;

import java.sql.SQLException;
import java.util.List;
import java.util.Optional;

public interface IService<T> {
    void ajouter(T entity) throws SQLException;

    void modifier(T entity) throws SQLException;

    void supprimer(int id) throws SQLException;

    List<T> afficher() throws SQLException;

    Optional<T> getById(int id) throws SQLException;
}
