package model;

public class BookCopy {
    private Integer id;

    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    @Override
    public String toString() {
        return "Copy #" + (id == null ? "new" : id);
    }
}
