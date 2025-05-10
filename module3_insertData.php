<!DOCTYPE html>
<html>
    <body>
        <?php 
        $name = $_POST["name"];
        $age = $_POST["age"];
        $gender = $_POST["gender"];
        $title = $_POST["title"];
        $hobby = is_array($_POST["hobby"]) ? implode(", ", $_POST["hobby"]) : $_POST["hobby"];
        $comment = $_POST["comment"];

        // to make a connection with database
	$link = mysqli_connect("localhost", "root") or die(mysqli_connect_error());

	// to select the targeted database
	mysqli_select_db($link, "mydb") or die(mysqli_error());

    // to create a query to be executed in sql
	$query = "insert into User values('', '$name', '$age', '$gender', '$title', '$hobby', '$comment')"  
    or die(mysqli_connect_error());


    // to run sql query in database
    $result = mysqli_query($link, $query);
 
    //Check whether the insert was successful or not
    if($result) 
        {
        
            echo "<script type='text/javascript'> 
        alert('Data inserted successfully!');
        window.location.href = 'Lab7_Q3_display.php';
      </script>";
            
}
else 
    {
            
        die("Insert failed");
    }

        ?>
    </body>
</html>

