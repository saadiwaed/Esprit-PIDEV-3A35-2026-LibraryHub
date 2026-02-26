from fastapi import FastAPI
from pydantic import BaseModel
from recommender import recommend, init_recommender

app = FastAPI()

class Prompt(BaseModel):
    text: str

@app.on_event("startup")
def startup_event():
    init_recommender()

@app.post("/recommend")
def recommend_books(prompt: Prompt):
    return {"books": recommend(prompt.text)}