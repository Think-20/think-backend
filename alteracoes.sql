INSERT INTO `companybook`.`job_activity`
(`id`,
`description`,
`no_params`,
`redirect_after_save`,
`fixed_duration`,
`fixed_budget_value`,
`min_duration`,
`max_duration`,
`max_budget_value_per_day`,
`max_duration_value_per_day`,
`next_period`,
`next_day`,
`counter`,
`initial`,
`keep_responsible`,
`visible`,
`modification_id`,
`option_id`,
`min_budget_value_to_more_days`)
VALUES
(21,
'Reunião',
0,
NULL,
0.2,
0,
0,
0,
0,
1,
0,
0,
0,
0,
0,
1,
NULL,
NULL,
0);

INSERT INTO job_activity_employee (employee_id, job_activity_id) VALUES (21, 21);

#Compartilhar duração de orçamento com reunião
INSERT INTO job_activity_share_duration (from_id, to_id) VALUES (21, 2);
INSERT INTO job_activity_share_duration (from_id, to_id) VALUES (2, 21);

