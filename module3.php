<!DOCTYPE html>
<html>
    <head>
        <title>Attendance Slot</title>
    </head>
    <body>
        <form action="module3_insertData.php" method="post">
            <hr>
            <label>StudentID</label>
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