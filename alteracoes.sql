insert into functionality values (null, '/client/import', 'Importar clientes');


insert into user_functionality (user_id, functionality_id)
		select 
			user.id,
			(select id from functionality where url = '/client/import')
        from user left join employee on employee.id = user.employee_id where employee.department_id IN (1,2,4)