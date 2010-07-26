<?php

/**
 * EmailReader
 *
 * Access email from an IMAP or POP account.
 * Currenly only supports fetching attachments.
 *
 * @see http://github.com/driverdan/emailreader
 * @see http://driverdan.com
 *
 * @author Dan DeFelippi
 * @license MIT
 * @todo Add additional 
 */
class EmailReader {
	// Stores mailbox stream
	private $mbox;
	
	// Email part types. Accessed via array position, do not reorder.
	var $partTypes = array(
		"text",
		"multipart",
		"message",
		"application",
		"audio",
		"image",
		"video",
		"other",
	);
	
	/**
	 * Constructor opens connection to the server.
	 *
	 * @see http://www.php.net/manual/en/function.imap-open.php
	 *
	 * @param string $host Host connection string.
	 * @param string $user Username
	 * @param string $password Password
	 *
	 * @return bool Returns true on success, false on failure.
	 */
	function __construct($host, $user, $password) {
		return (bool)($this->mbox = imap_open($host, $user, $password));
	}
	
	/**
	 * Destructor closes server connection.
	 */
	function __destruct() {
		imap_close($this->mbox);
	}
	
	/**
	 * Decodes a message based on encoding type.
	 *
	 * @param string $message Email message part.
	 * @param int $encoding Encoding type.
	 */
	function decode($message, $encoding) {
		switch ($encoding) {
			case 0:
			case 1:
				$message = imap_8bit($message);
			break;
			
			case 2:
				$message = imap_binary($message);
			break;
			
			case 3:
			case 5:
				$message = imap_base64($message);
			break;
			
			case 4:
				$message = imap_qprint($message);
			break;
		}
		
		return $message;
	}
	
	/**
	 * Saves all email attachments for all emails. Uses original filenames.
	 *
	 * @todo Handle duplicate filenames.
	 *
	 * @param string $path Directory path to save files in.
	 * @param bool $delete Delete all emails after processing by default. Set to false to not delete them.
	 */
	function saveAttachments($path, $delete = true) {
		$numMessages = $this->getNumMessages();
		
		// Append slash to path if missing
		if ($path[strlen($path) - 1] != '/') {
			$path .= '/';
		}
		
		// Loop through all messages
		for ($msgId = 1; $msgId <= $numMessages; $msgId++) {
			$structure = imap_fetchstructure($this->mbox, $msgId, FT_UID);    
			$fileNum = 2;
			
			// Loop through all email parts
			foreach ($structure->parts as $part) {
				// If it's an attachment save it
				if ($part->disposition == "ATTACHMENT") {
					$filename = $part->dparameters[0]->value;
					
					// Get the body and decode it
				  	$body = imap_fetchbody($this->mbox, $msgId, $fileNum);
					$data = self::decode($body, $part->type);
					
					// Save the file
					$fp = fopen("$path$filename", "w");
					fputs($fp, $data);
					fclose($fp);
					
					$fileNum++;
				}
			}
			
			if ($delete) {
				$this->delete($msgId);
			}
		}
		
		// Expunging is required if messages were deleted
		if ($delete) {
			$this->expunge();
		}
	}
	
	/**
	 * Gets the number of messages in a mailbox.
	 *
	 * @return int Number of messages in the mailbox.
	 */
	function getNumMessages() {
		return imap_num_msg($this->mbox);
	}
	
	/**
	 * Deletes a message.
	 *
	 * @param int $id ID of message to delete.
	 * @param bool $expunge Optionally expunge mailbox.
	 */
	function delete($id, $expunge = false) {
		imap_delete($this->mbox, $id);
		
		if ($expunge) {
			$this->expunge();
		}
	}
	
	/**
	 * Expunge a mailbox. Call after deleting messages.
	 */
	function expunge() {
		imap_expunge($this->mbox);
	}
}