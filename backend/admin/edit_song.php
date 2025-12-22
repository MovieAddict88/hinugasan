<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "../includes/db.php";

$title = $artist = $video_source = "";
$title_err = $artist_err = $video_source_err = "";

if (isset($_POST["id"]) && !empty($_POST["id"])) {
    $id = $_POST["id"];

    $input_title = trim($_POST["title"]);
    if (empty($input_title)) {
        $title_err = "Please enter a title.";
    } else {
        $title = $input_title;
    }

    $input_artist = trim($_POST["artist"]);
    if (empty($input_artist)) {
        $artist_err = "Please enter an artist.";
    } else {
        $artist = $input_artist;
    }

    $input_video_source = trim($_POST["video_source"]);
    if (empty($input_video_source)) {
        $video_source_err = "Please enter a video source.";
    } else {
        $video_source = $input_video_source;
    }

    if (empty($title_err) && empty($artist_err) && empty($video_source_err)) {
        $sql = "UPDATE songs SET title=?, artist=?, video_source=? WHERE id=?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssi", $param_title, $param_artist, $param_video_source, $param_id);

            $param_title = $title;
            $param_artist = $artist;
            $param_video_source = $video_source;
            $param_id = $id;

            if ($stmt->execute()) {
                header("location: dashboard.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
} else {
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        $id =  trim($_GET["id"]);

        $sql = "SELECT * FROM songs WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $param_id);

            $param_id = $id;

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $row = $result->fetch_array(MYSQLI_ASSOC);

                    $title = $row["title"];
                    $artist = $row["artist"];
                    $video_source = $row["video_source"];
                } else {
                    header("location: error.php");
                    exit();
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    } else {
        header("location: error.php");
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Song</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .wrapper {
            width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <h2 class="mt-5">Update Record</h2>
                    <p>Please edit the input values and submit to update the song record.</p>
                    <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($title); ?>">
                            <span class="invalid-feedback"><?php echo $title_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Artist</label>
                            <input type="text" name="artist" class="form-control <?php echo (!empty($artist_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($artist); ?>">
                            <span class="invalid-feedback"><?php echo $artist_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Video Source</label>
                            <input type="text" name="video_source" class="form-control <?php echo (!empty($video_source_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($video_source); ?>">
                            <span class="invalid-feedback"><?php echo $video_source_err; ?></span>
                        </div>
                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="dashboard.php" class="btn btn-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
