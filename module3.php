<!DOCTYPE html>
<html>
    <head>
        <title>Lab 7 Question 3</title>
    </head>
    <body>
        <form action="Lab7_Q2_actionSQL.php" method="post">
            <hr>
            <label>Name:</label>
            <input type="text" name="name">
            <br>
            <label>Age:</label>
            <input type="number" name="age">
            <br>
            <label>Gender:</label>
            <input type="radio" name="gender" value="male">Male
            <input type="radio" name="gender" value="female">Female
            <br>
            <label>Title:</label>
            <input type="checkbox" name="title" value="prof">Prof
            <input type="checkbox" name="title" value="Dr">Dr
            <br>
            <label>Hobby:</label>
            <select name="hobby[]" multiple>
                <option value="reading">reading</option>
                <option value="swimming">swimming</option>
                <option value="basketball">basketball</option>
                <option value="football">football</option>
            </select>
            <br>
            <label>Comments: </label>
            <textarea name="comment" rows = "4" cols = "36"></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>
        <hr>
    </body>
</html>