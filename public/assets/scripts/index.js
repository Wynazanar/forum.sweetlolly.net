let category_select = document.querySelector("#parent_id");
category_select.addEventListener('change', function() {
    updateText();
});

document.querySelector('#title').addEventListener('input', function() {
    updateText();
});

let moderate_topics = document.querySelector('#moderate_topics');
moderate_topics.addEventListener('change', function() {
    updateText();
});

let allow_attachments = document.querySelector('#allow_attachments');
allow_attachments.addEventListener('change', function() {
    updateText();
});

function updateText() {
    let form_notes = document.querySelector("#form-notes");

    let category = document.querySelector("#parent_id").options[category_select.selectedIndex].text;
    
    if (category == '— Верхний уровень (категория) —') {
        form_notes.textContent = `Появится новая категория \«${document.querySelector('#title').value}\».`;
    }
    else {
        form_notes.textContent = `Новый раздел \«${document.querySelector('#title').value}\» появится в категории \«${category}\».`;
    }


    if(moderate_topics.checked) {
        form_notes.textContent += ` Проверка тем включена.`;
    } else {
        form_notes.textContent += ` Проверка тем выключена.`;
    }
    
    if(allow_attachments.checked) {
        form_notes.textContent += ` Вложения разрешены.`;
    } else {
        form_notes.textContent += ` Вложения запрещены.`;
    }

    form_notes.textContent += ` Права можно изменить позже.`;
}

updateText();