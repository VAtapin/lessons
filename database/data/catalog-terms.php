<?php

return array_map(fn (array $term): array => ['kind' => $term[0], 'key' => $term[1], 'labels' => ['ru' => $term[2], 'de' => $term[3]]], [
    ['age', '5-7', '5–7 лет', '5–7 Jahre'], ['age', '8-10', '8–10 лет', '8–10 Jahre'], ['age', '11-14', '11–14 лет', '11–14 Jahre'], ['age', '15+', '15+ лет', 'Ab 15 Jahren'], ['age', 'adults', 'Взрослые', 'Erwachsene'],
    ['topic', 'bible', 'Библия', 'Bibel'], ['topic', 'parables', 'Притчи', 'Gleichnisse'], ['topic', 'holidays', 'Праздники', 'Feste'], ['topic', 'mercy', 'Милосердие', 'Barmherzigkeit'], ['topic', 'family', 'Семья', 'Familie'], ['topic', 'prayer', 'Молитва', 'Gebet'],
    ['audience', 'school', 'Школа', 'Schule'], ['audience', 'sunday-school', 'Воскресная школа', 'Sonntagsschule'], ['audience', 'family', 'Семья', 'Familie'], ['audience', 'group', 'Группа', 'Gruppe'], ['audience', 'children', 'Дети', 'Kinder'], ['audience', 'adults', 'Взрослые', 'Erwachsene'],
    ['format', 'lesson', 'Урок', 'Unterricht'], ['format', 'presentation', 'Презентация', 'Präsentation'], ['format', 'game', 'Игра', 'Spiel'], ['format', 'worksheet', 'Рабочий лист', 'Arbeitsblatt'], ['format', 'interactive', 'Интерактив', 'Interaktiv'], ['format', 'notes', 'Конспект', 'Entwurf'], ['format', 'questions', 'Вопросы', 'Fragen'],
]);
