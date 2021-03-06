<?php
   /******************************************************************************
 * Photofunktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id:   Id des Albums
 * job:      delete - loeschen eines Bildes
 *           rotate - drehen eines Bildes
 * direction: left  - Bild nach links drehen
 *            right - Bild nach rechts drehen
 * photo_nr:  Nr des Bildes welches verarbeitet werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');
require_once('../../system/classes/image.php');

// Initialize and check the parameters
$getPhotoId   = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getJob       = admFuncVariableIsValid($_GET, 'job', 'string', null, true, array('delete', 'rotate'));
$getPhotoNr   = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', null, true);
$getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', null, false, array('left', 'right'));

if ($gPreferences['enable_photo_module'] == 0)
{
	// das Modul ist deaktiviert
	$gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
	$gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

//Loeschen eines Thumbnails
// photo_album : Referenz auf Objekt des relevanten Albums
// pic_nr      : Nr des Bildes dessen Thumbnail geloescht werden soll
function deleteThumbnail(&$photo_album, $pic_nr)
{
    if(is_numeric($pic_nr))
    {
        //Ordnerpfad zusammensetzen
        $photo_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id').'/thumbnails/'.$pic_nr.'.jpg';
		
        //Thumbnail loeschen
        if(file_exists($photo_path))
        {
            chmod($photo_path, 0777);
            unlink($photo_path);
        }
    }
}

//Loeschen eines Bildes
function deletePhoto($pho_id, $pic_nr)
{
    global $gDb;

    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($pic_nr))
    {
        // einlesen des Albums
        $photo_album = new TablePhotos($gDb, $pho_id);
        
        //Speicherort
        $album_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id');
        
        //Bilder loeschen
        if(file_exists($album_path.'/'.$pic_nr.'.jpg'))
        {
            chmod($album_path.'/'.$pic_nr.'.jpg', 0777);
            unlink($album_path.'/'.$pic_nr.'.jpg');
        }

        // Umbenennen der Restbilder und Thumbnails loeschen
        $new_pic_nr = $pic_nr;
        $thumbnail_delete = false;

        for($act_pic_nr = 1; $act_pic_nr <= $photo_album->getValue('pho_quantity'); $act_pic_nr++)
        {
            if(file_exists($album_path.'/'.$act_pic_nr.'.jpg'))
            {
                if($act_pic_nr > $new_pic_nr)
                {
                    chmod($album_path.'/'.$act_pic_nr.'.jpg', 0777);
                    rename($album_path.'/'.$act_pic_nr.'.jpg', $album_path.'/'.$new_pic_nr.'.jpg');
                    $new_pic_nr++;
                }                
            }
            else
            {
                $thumbnail_delete = true;
            }
            
            if($thumbnail_delete)
            {
                // Alle Thumbnails ab dem geloeschten Bild loeschen
                deleteThumbnail($photo_album, $act_pic_nr);
            }
        }//for

        // Aendern der Datenbankeintaege
        $photo_album->setValue('pho_quantity', $photo_album->getValue('pho_quantity')-1);
        $photo_album->save();
    }
};


// Foto um 90° drehen
if($getJob == 'rotate')
{
    // nur bei gueltigen Uebergaben weiterarbeiten
    if(strlen($getDirection) > 0)
    {
        //Aufruf des ggf. uebergebenen Albums
        $photo_album = new TablePhotos($gDb, $getPhotoId);

        //Thumbnail loeschen
        deleteThumbnail($photo_album, $getPhotoNr);
        
        //Ordnerpfad zusammensetzen
        $photo_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id'). '/'. $getPhotoNr. '.jpg';
        
        // Bild drehen
        $image = new Image($photo_path);
        $image->rotate($getDirection);
        $image->delete();
    }    
}
elseif($getJob == 'delete')
{
    // das entsprechende Bild wird physikalisch und in der DB geloescht
    deletePhoto($getPhotoId, $getPhotoNr);
    
    //Neu laden der Albumdaten
    $photo_album = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photo_album->readData($getPhotoId);
    }

    $_SESSION['photo_album'] =& $photo_album;
    
    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
?>