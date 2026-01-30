<?php
include 'db.php';
include 'includes/header.php';

if(isset($_POST['send'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $msg = $_POST['message'];

    $stmt = $conn->prepare(
        "INSERT INTO feedback(name,email,message) VALUES (?,?,?)"
    );
    $stmt->bind_param("sss",$name,$email,$msg);
    $stmt->execute();

    echo "<p style='color:green'>Message sent successfully!</p>";
}
?>

<h2>Contact Us</h2>

<form method="post">
    <input type="text" name="name" placeholder="Your Name" required><br><br>
    <input type="email" name="email" placeholder="Your Email" required><br><br>
    <textarea name="message" placeholder="Your Message" required></textarea><br><br>
    <button name="send">Send Message</button>
</form>

<?php include 'includes/footer.php'; ?>

