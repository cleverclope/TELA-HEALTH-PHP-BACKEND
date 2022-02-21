<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    class Attendance {
        private ?PDO $connection;

        public function __construct() {
            $this->connection = connect_database();
        }

        public function save_check_in() {
            try {
                $statement = $this->connection->prepare("select attendance_date from users_attendance where user_id = :user_id 
                               and attendance_date = :attendance_date limit 1");
                $statement->execute(['user_id' => $_POST['user_id'], 'attendance_date' => now_date]);
                if ($statement->fetch()) {
                    return server_response(2, 'User already checked in');
                }

                $statement = $this->connection->prepare("insert into users_attendance (user_id, attendance_date, time_in, time_out) 
                                VALUES (:user_id, :attendance_date, :time_in, null)");
                $statement->execute(['user_id' => $_POST['user_id'], 'attendance_date' => now_date, 'time_in' => now_time]);

                return server_response(1, 'Checked-In successfully');
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function save_check_out() {
            try {
                $statement = $this->connection->prepare("select attendance_date from users_attendance where user_id = :user_id 
                               and attendance_date = :attendance_date and time_out is not null limit 1");
                $statement->execute(['user_id' => $_POST['user_id'], 'attendance_date' => now_date]);
                if ($statement->fetch()) {
                    return server_response(2, 'User already checked out');
                }

                $statement = $this->connection->prepare("update users_attendance set time_out = :time_out
                                where user_id = :user_id and attendance_date = :attendance_date");
                $statement->execute(['user_id' => $_POST['user_id'], 'attendance_date' => now_date, 'time_out' => now_time]);

                return server_response(1, 'Checked-In successfully');
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_attendances() {
            try {
                $statement = $this->connection->prepare("select u.user_id, attendance_date, time_in, coalesce(time_out, '') as time_out, 
                                        last_name, first_name, face_path, face_gender, date_of_birth, user_type,
                                        coalesce(s1.school_name, s2.school_name, '') as school_name
                                    from users_attendance ua 
                                        inner join users u on ua.user_id = u.user_id
                                        
                                        left join users_students us1 on u.user_id = us1.user_id
                                        left join schools s1 on us1.school_id = s1.school_id
                                        
                                        left join users_staff us2 on u.user_id = us2.user_id
                                        left join schools s2 on us2.school_id = s2.school_id
                                    order by attendance_date, school_name, user_type, last_name, first_name");
                $statement->execute();
                $attendances = $statement->fetchAll();
                return server_response(1, 'Attendance List', ['attendances' => $attendances]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }
    }
