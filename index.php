<?php

//set your dbconfig
require('../private/dbconfig.php');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);
$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["encryptedNote"])) {
        $content = $_POST["encryptedNote"];
        $unique_link = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT INTO notes (note_content, unique_link) VALUES (?, ?)");
        $stmt->execute([$content, $unique_link]);

        echo json_encode(['link' => "https://" . $_SERVER['HTTP_HOST'] . "/?link=" . $unique_link]);
        exit;
    }

    if (isset($_POST["linkToFetch"])) {
        $link = $_POST["linkToFetch"];
        $stmt = $pdo->prepare("SELECT note_content FROM notes WHERE unique_link = ?");
        $stmt->execute([$link]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($note) {
            $encryptedMessage = htmlspecialchars($note["note_content"]);
            echo json_encode(['note' => $encryptedMessage]);

            $deleteStmt = $pdo->prepare("DELETE FROM notes WHERE unique_link = ?");
            $deleteStmt->execute([$link]);
        } else {
            echo json_encode(['error' => 'Note not found']);
        }

        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <link rel="icon" type="image/x-icon" sizes="16x16" href="images/favicon-16x16.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sichere Einmal-Notiz</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="bg-white p-8 mx-auto text-center rounded-lg shadow-md w-full max-w-md">

        <div id="createNote">
            <textarea id="noteContent" rows="5" class="w-full p-3 border rounded mb-4"
                placeholder="Geb deine Notiz hier ein..."></textarea>
            <button onclick="saveNote()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">Save
                note</button>
        </div>

        <div id="linkDisplay" style="display: none;">
            <input type="text" id="generatedLink" class="w-full p-3 border rounded mb-4" readonly>
            <button onclick="copyLink()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">copy
                link</button>
        </div>

        <div id="noteAction" style="display: none;">
            <button onclick="showNote()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">Show note
                and delte it</button>
        </div>

        <div id="noteDisplay" style="display: none;">
            <div class="mb-4 font-bold text-blue-400">Message content:</div>
            <div id="decryptedNote" class="mb-4 font-bold"></div>
        </div>

        <div id="backToStart" style="display: none;">
            <button onclick="redirectToPrivnote()"
                class="bg-blue-500 hover:bg-blue-600 mt-5 text-white py-2 px-4 rounded">to startpage</button>
        </div>

        <div id="errorInfo" class="pt-10" style="display: none;">
            <div class="mb-4 font-bold text-red-400">The note was not found, already read or never existed
                existed</div>
        </div>

        <!-- Info Button and Info-window -->
        <button id="infoButton" class="mt-5 hover:text-blue-800 font-bold text-gray-400 mb-4" onclick="toggleInfo()">Wie
            geht das?</button>

        <div id="infoBox" class="border p-4 mb-4 rounded bg-gray-100" style="display: none;">
            <h2 class="font-bold mb-2">How it is working?</h2>
            <p>
                The system allows the generation of a note that can be viewed only once. Immediately after
                the note is encrypted before it is sent to the server. The
                decryption key is not known to the server at any time. This ensures that no person,
                including server administrators, has access to the decrypted content of the note.
            </p>
            <p class="mt-2">
                When the provided link is accessed and the ad button is clicked, the note will be
                is retrieved and immediately deleted from the server. Since the decryption key is known only in the
                user's original
                only known in the user's original browser, only that browser can decrypt and display the note. On this
                No cookies are used on this page. If the link provided is lost, there is absolutely no way to recover
                the note.
                there is absolutely no way to recover or decrypt the note.
                decrypt it.
            </p>
            <button class="bg-red-500 hover:bg-red-600 text-white py-1 px-2 mt-4 rounded"
                onclick="toggleInfo()">Close</button>
        </div>


    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let urlParams = new URLSearchParams(window.location.search);
            let keyFragment = window.location.hash.substring(1);

            if (urlParams.has('link') && keyFragment) {
                document.getElementById('createNote').style.display = "none";
                document.getElementById('noteAction').style.display = "block";
            }
        });

        function saveNote() {
            let noteContent = document.getElementById('noteContent').value;
            let key = CryptoJS.lib.WordArray.random(16).toString();
            let encryptedNote = CryptoJS.AES.encrypt(noteContent, key).toString();
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "index.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    let response = JSON.parse(this.responseText);
                    let link = response.link;
                    document.getElementById('generatedLink').value = link + "#" + key;
                    document.getElementById('createNote').style.display = "none";
                    document.getElementById('linkDisplay').style.display = "block";
                }
            };
            xhr.send("encryptedNote=" + encodeURIComponent(encryptedNote));
        }

        function showNote() {
            let link = new URLSearchParams(window.location.search).get('link');
            let key = window.location.hash.substring(1);

            if (key) { // Check if Key is there
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "index.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (this.readyState === 4 && this.status === 200) {
                        let response = JSON.parse(this.responseText);
                        if (response.note) {
                            let decryptedNote = CryptoJS.AES.decrypt(response.note, key).toString(CryptoJS.enc.Utf8);
                            decryptedNote = decryptedNote.replace(/\n/g, '<br>');
                            document.getElementById('decryptedNote').innerHTML = decryptedNote;
                            document.getElementById('noteAction').style.display = "none";
                            document.getElementById('noteDisplay').style.display = "block";
                            document.getElementById('backToStart').style.display = "block";
                        } else {
                            document.getElementById('errorInfo').style.display = "block";
                        }
                    }
                };
                xhr.send("linkToFetch=" + link);
            } else {
                // If no key...
                document.getElementById('errorInfo').innerHTML = "<div class=\"mb-4 font-bold text-red-400\">Error: The key was not provided.</div>";
                document.getElementById('errorInfo').style.display = "block";
            }
        }

        function redirectToPrivnote() {
            //set your startpage
            window.location.href = "https://<YOURSTARTPAGE>";
        }

        function copyLink() {
            let linkField = document.getElementById('generatedLink');
            linkField.select();
            document.execCommand("copy");
        }

        function toggleInfo() {
            let infoBox = document.getElementById('infoBox');
            if (infoBox.style.display === "none") {
                infoBox.style.display = "block";
            } else {
                infoBox.style.display = "none";
            }
        }

    </script>

</body>

</html>