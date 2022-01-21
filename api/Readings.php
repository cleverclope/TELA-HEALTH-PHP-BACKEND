<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    class Readings {
        private ?PDO $connection;

        public function __construct() {
            $this->connection = connect_database();
        }

        public function save_readings() {
            try {
                $statement = $this->connection->prepare("insert into readings (student_id, time_read, temperature) VALUES 
                                    (student_id, time_read, temperature)");
                $statement->execute([
                    'student_id' => $_POST['student_id'], 'temperature' => $_POST['temperature'], 'time_read' => current_time
                ]);
                return server_response(1, 'Reading saved successfully');
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function save_readings_data() {
            try {
                $statement = $this->connection->prepare("select reading_id from readings_covid 
                                where reading_id = :reading_id limit 1");
                $statement->execute(['reading_id' => $_POST['reading_id']]);
                if ($statement->fetch()) {
                    $statement = $this->connection->prepare("update readings_covid set covid_symptoms = :covid_symptoms, 
                                            onset_date = :onset_date, actions_taken = :actions_taken, where_managed = :where_managed, 
                                            where_tested = :where_tested, results_date = :results_date, results = :results, 
                                            date_healed = :date_healed
                                        where reading_id = :reading_id");
                } else {
                    $statement = $this->connection->prepare("insert into readings_covid (reading_id, covid_symptoms, 
                                    onset_date, actions_taken, where_managed, where_tested, results_date, results, date_healed) values(
                                    :reading_id, :covid_symptoms, :onset_date, :actions_taken, :where_managed, :where_tested, 
                                    :results_date, :results, :date_healed) ");
                }

                $statement->execute([
                    'reading_id'     => $_POST['reading_id'],
                    'covid_symptoms' => $_POST['covid_symptoms'],
                    'onset_date'     => $_POST['onset_date'],
                    'actions_taken'  => $_POST['actions_taken'],
                    'where_managed'  => $_POST['where_managed'],
                    'where_tested'   => $_POST['where_tested'],
                    'results_date'   => $_POST['results_date'],
                    'results'        => $_POST['results'],
                    'date_healed'    => $_POST['date_healed']
                ]);

                return server_response(1, 'Readings data saved successfully');
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_readings() {
            try {
                $statement = $this->connection->prepare("select results_id, s.student_id, s.school_id, school_name,
                                            temperature, time_read
                                        from readings
                                            inner join students s on readings.student_id = s.student_id
                                            inner join schools s2 on s.school_id = s2.school_id
                                            left join readings_covid rc on readings.results_id = rc.reading_id");
                $statement->execute();
                return server_response(1, 'Readings Data', ['readings' => $statement->fetchAll()]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_report() {
            try {
                $output = [];
                $statement = $this->connection->prepare("select where_tested, results, 
                                        coalesce(date_healed, '') as date_healed, where_managed
                                    from readings_covid 
                                        inner join readings r on readings_covid.reading_id = r.results_id
                                    where time_read between :min_date and :max_date");

                for ($date = $_GET['min_date']; strtotime($date) <= strtotime($_GET['max_date']);) {
                    $statement->execute(['min_date' => $date, 'max_date' => sprintf("%s 23:59:59", $date)]);
                    $readings = $statement->fetchAll();

                    $screens = $referred = $not_tested = $positive = $negative = $active = $managed = 0;
                    foreach ($readings as $reading) {
                        if ($reading['where_tested'] == 'School') {
                            $screens += $screens;
                        } else if ($reading['where_tested'] == 'Referred') {
                            $referred += $referred;
                        } else {
                            $not_tested++;
                        }

                        if ($reading['results'] == 'Positive') {
                            $positive++;
                        } else if ($reading['results'] == 'Negative') {
                            $negative++;
                        }

                        if ($reading['where_managed'] == 'School') {
                            $managed++;
                        }
                        if ($reading['date_healed'] == '') {
                            $active++;
                        }
                    }

                    $output[] = [
                        'date'       => $date,
                        'symptoms'   => count($readings), //the total number of any school Individuals with Covid-19 related Symptoms
                        'screens'    => $screens, //the total number of Screened individuals at school
                        'referred'   => $referred, //the total number of individuals Referred for testing
                        'not_tested' => $not_tested, //the total number of individuals Referred for testing
                        'positive'   => $positive, //the total number of Positive Covid tests Cases
                        'negative'   => $negative, //the total number of Negative Covid tests Cases
                        'active'     => $active, //the total number of Positive Covid tests Cases
                        'managed'    => $managed //total number of COVID 19 cases Managed at school
                    ];

                    $date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
                }

                return server_response(1, 'Readings Data', ['reports' => $output]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }
    }
