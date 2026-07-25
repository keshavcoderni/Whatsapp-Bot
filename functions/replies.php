<?php

function getReply($conn,$key){

    $stmt = $conn->prepare(
        "SELECT * FROM bot_replies
         WHERE reply_key=?"
    );

    $stmt->bind_param("s",$key);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}