update functionality set url = REPLACE(url, 'job', 'briefing') WHERE id IN (69,70);
update functionality set description = REPLACE(description, 'job', 'briefing') WHERE id IN (69,70);

insert into functionality (url, description) values('/budget/edit-available-date', 'Mudar data disponível do orçamento');
insert into functionality (url, description) values('/my-budget/edit-available-date', 'Mudar data disponível dos orçamentos próprios');
