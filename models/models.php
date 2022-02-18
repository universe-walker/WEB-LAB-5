<?php

require_once "db.php";

class Post extends DB
{
    public function getActivePostsWithOffset($offset, $limit=2)
    {
        $sth = $this->dbh->prepare("
        SELECT P.post_id, P.title, P.original_title, P.poster, P.text_post, P.date, U.name
            FROM post AS P
            JOIN `user` AS U ON P.user_id=U.user_id
            WHERE P.is_published
            ORDER BY P.post_id DESC
            LIMIT ?, ?
        ");
        $sth->bindValue(1, $offset, PDO::PARAM_INT);
        $sth->bindValue(2, $limit, PDO::PARAM_INT);
        $sth->execute();
        return $sth;
    }

    public function getPostById($id)
    {
        $sth = $this->dbh->prepare("
        SELECT P.post_id, P.title, P.original_title, P.poster, P.trailer_link, 
               P.release_year, P.text_post, P.rating, P.date, U.name
                FROM post AS P
                JOIN `user` AS U ON P.user_id=U.user_id
                WHERE P.is_published AND P.post_id=:post_id;
        ");
        $sth->bindValue(':post_id', $id, PDO::PARAM_INT);
        $sth->execute();
        return $sth->fetch();
    }

    public function addPost(
        int $user_id,
        string $title,
        string $original_title,
        string $poster_path,
        string $trailer_link,
        string $text_post,
        bool $is_published,
        int $release_year,
        int $rating
    ): int {
        $sth = $this->dbh->prepare(
            "INSERT INTO Post 
            (user_id,
            title,
            original_title,
            poster,
            trailer_link,
            text_post,
            DATE,
            is_published,
            release_year,
            rating)
         VALUES
            (:user_id,
             :title,
             :original_title,
             :poster,
             :trailer_link,
             :text_post,
             NOW(),
             :is_published,
             :release_year,
             :rating);
        "
        );
        $sth->bindValue(":user_id", $user_id, PDO::PARAM_INT);
        $sth->bindValue(":title", $title);
        $sth->bindValue(":original_title", $original_title);
        $sth->bindValue(":poster", $poster_path);
        $sth->bindValue(":trailer_link", $trailer_link);
        $sth->bindValue(":text_post", $text_post);
        $sth->bindValue(":is_published", $is_published, PDO::PARAM_BOOL);
        $sth->bindValue(":release_year", $release_year, PDO::PARAM_INT);
        $sth->bindValue(":rating", $rating, PDO::PARAM_INT);

        $sth->execute();

        return $this->dbh->lastInsertId();
    }
}


class Comment extends DB
{
    public function getAllActiveComments($post_id)
    {
        $sth = $this->dbh->prepare(
            "SELECT
                C.id,
                C.text,
                C.date_time,
                C.is_active,
                U.name,
                U.avatar
            FROM
                `comment` AS C
            JOIN post AS P
            ON
                C.post_id = P.post_id
            JOIN `user` AS U
            ON
                C.user_id = U.user_id
            WHERE
                P.post_id = :post_id AND C.is_active
        ");
        $sth->bindValue(":post_id", $post_id, PDO::PARAM_INT);
        $sth->execute();
        return $sth;
    }

    public function insertNewCommentAndReturnItId($post_id, $text, $user_id) 
    {
        try {
            $sth = $this->dbh->prepare(
                "INSERT INTO comment (post_id, user_id, text, date_time, is_active) VALUES
                    (:post_id, :user_id, :text, NOW(), 1) 
            ");
            $sth->bindValue(":post_id", $post_id, PDO::PARAM_INT);
            $sth->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $sth->bindValue(":text", htmlspecialchars($text));

            $sth->execute();
        } catch (PDOException $e) {
            return false;
        }

        return $this->dbh->lastInsertId();
    }

    public function getCommentById($id)
    {
        $sth = $this->dbh->prepare(
            "SELECT
                C.id,
                C.text,
                C.date_time,
                U.name,
                U.avatar
            FROM
                `comment` AS C
            JOIN USER AS U
            ON
                C.user_id = U.user_id
            WHERE
                C.id = :comment_id
        ");

        $sth->bindValue(":comment_id", $id, PDO::PARAM_INT);
        $sth->execute();

        return $sth->fetch();
    }
}

class User extends DB
{
    public function getUserById($id)
    {
        $sth = $this->dbh->prepare("
            SELECT * FROM User
            WHERE user_id=:user_id
        ");

        $sth->bindValue(":user_id", $id, PDO::PARAM_INT);

        $sth->execute();

        return $sth->fetch();
    }

    public function getUserByEmail($email)
    {
        $sth = $this->dbh->prepare("
        SELECT * FROM User
        WHERE email=:email
        ");

        $sth->execute(array(":email" => $email));

        return $sth->fetch();
    }

    public function getUserIfPasswordVerify($email, $password)
    {
        $user = $this->getUserByEmail($email);
        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                return $user;
            }
        }

        return false;
    }

    public function getUserIdByEmail($email)
    {
        $sth = $this->dbh->prepare("
        SELECT user_id FROM User
        WHERE email=:email
        ");

        $sth->execute(array(":email" => $email));

        return $sth->fetch()[0];
    }

    public function isEmailFree($email)
    {
        $sth = $this->dbh->prepare("SELECT COUNT(*) FROM User WHERE email=:email");
        $sth->execute(array(":email" => $email));

        $count = (int) $sth->fetch()[0];

        return $count === 0;
    }

    public function save($name, $email, $phone, $password)
    {
        try {
            $sth = $this->dbh->prepare("
            INSERT INTO User (name, email, phone, password_hash) VALUES
                (:name, :email, :phone, :password_hash)
            ");

            $save_name = htmlspecialchars($name);
            $save_email = htmlspecialchars($email);
            $save_phone = htmlspecialchars($phone);

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sth->execute(array(":name" => $save_name, ":email" => $save_email, ":phone" => $save_phone, ":password_hash" => $password_hash));
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }
}
