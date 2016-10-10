<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\ArtifactsFolders\ArtifactsFoldersPluginInfo;
use Tuleap\ArtifactsFolders\Folder\ArtifactPresenterBuilder;
use Tuleap\ArtifactsFolders\Folder\Controller;
use Tuleap\ArtifactsFolders\Folder\Router;
use Tuleap\ArtifactsFolders\Nature\NatureInFolderPresenter;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureDao;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureIsChildLinkRetriever;
use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NaturePresenterFactory;
use Tuleap\ArtifactsFolders\Folder\FolderUsageRetriever;
use Tuleap\ArtifactsFolders\Folder\Dao;
use Tuleap\ArtifactsFolders\Folder\ArtifactView;
use Tuleap\XML\PHPCast;

require_once 'autoload.php';
require_once 'constants.php';

class ArtifactsFoldersPlugin extends Plugin
{
    public function __construct($id)
    {
        parent::__construct($id);
        $this->setScope(self::SCOPE_PROJECT);
    }

    public function getHooksAndCallbacks()
    {
        if (defined('TRACKER_BASE_URL')) {
            $this->addHook(NaturePresenterFactory::EVENT_GET_ARTIFACTLINK_NATURES);
            $this->addHook(Tracker_Artifact_EditRenderer::EVENT_ADD_VIEW_IN_COLLECTION);
            $this->addHook(Event::JAVASCRIPT_FOOTER);
            $this->addHook(TrackerXmlImport::ADD_PROPERTY_TO_TRACKER);
            $this->addHook(Tracker_Artifact_XMLImport_XMLImportFieldStrategyArtifactLink::TRACKER_ADD_SYSTEM_NATURES);
            $this->addHook(Tracker_Artifact_XMLImport_XMLImportFieldStrategyArtifactLink::TRACKER_IS_NATURE_VALID);
            $this->addHook('cssfile');
            $this->addHook(Tracker_Artifact_ChangesetValue_ArtifactLinkDiff::HIDE_ARTIFACT);
            $this->addHook(NaturePresenterFactory::EVENT_GET_NATURE_PRESENTER);
            $this->addHook(Tracker_FormElement_Field_ArtifactLink::PREPEND_ARTIFACTLINK_INFORMATION);
        }

        return parent::getHooksAndCallbacks();
    }

    /**
     * @see Plugin::getDependencies()
     */
    public function getDependencies()
    {
        return array('tracker');
    }

    /**
     * @return PluginInfo
     */
    public function getPluginInfo()
    {
        if (! $this->pluginInfo) {
            $this->pluginInfo = new ArtifactsFoldersPluginInfo($this);
        }

        return $this->pluginInfo;
    }

    public function cssfile()
    {
        if (strpos($_SERVER['REQUEST_URI'], TRACKER_BASE_URL) === 0) {
            echo '<link rel="stylesheet" type="text/css" href="' . $this->getThemePath() . '/css/style.css" />';
        }
    }

    public function javascript_footer($params)
    {
        if (strpos($_SERVER['REQUEST_URI'], TRACKER_BASE_URL) === 0) {
            echo '</script>' . $this->getMinifiedAssetHTML() . '</script>';
        }
    }

    public function event_get_artifactlink_natures($params)
    {
        $params['natures'][] = new NatureInFolderPresenter();
    }

    /** @see Tracker_Artifact_EditRenderer::EVENT_ADD_VIEW_IN_COLLECTION */
    public function tracker_artifact_editrenderer_add_view_in_collection(array $params)
    {
        $user       = $params['user'];
        $request    = $params['request'];
        $artifact   = $params['artifact'];
        $collection = $params['collection'];

        $project = $artifact->getTracker()->getProject();
        if (! $this->isAllowed($project->getId())) {
            return;
        }

        $dao = new NatureDao();
        if ($this->canAddOurView($dao, $artifact, $project, $user)) {
            $view = new ArtifactView($artifact, $request, $user, $this->getPresenterBuilder());
            $collection->add($view);
        }
    }

    private function canAddOurView(NatureDao $dao, Tracker_Artifact $artifact, Project $project, PFUser $user)
    {
        $folder_usage_retriever = $this->getFolderUsageRetriever();

        return
            $folder_usage_retriever->projectUsesArtifactsFolders($project, $user)
            && $dao->hasReverseLinkedArtifacts($artifact->getId(), NatureInFolderPresenter::NATURE_IN_FOLDER);
    }

    /** @see TrackerXmlImport::ADD_PROPERTY_TO_TRACKER */
    public function add_property_to_tracker(array $params)
    {
        $xml_element = $params['xml_element'];
        $is_folder   = isset($xml_element['is_folder']) ? PHPCast::toBoolean($xml_element['is_folder']) : false;

        if ($is_folder) {
            $this->setFolderProperty($params['project'], $params['tracker_id'], $params['logger']);
        }
    }

    private function setFolderProperty(Project $project, $tracker_id, Logger $logger)
    {
        if (! $this->getFolderUsageRetriever()->doesProjectHaveAFolderTracker($project)) {
            if (! $this->getDao()->create($tracker_id)) {
                $logger->warn("Error while setting Folder flag for tracker $tracker_id.");
            }
        } else {
            $logger->warn("Cannot set tracker $tracker_id as a Folder tracker because you already have one defined for this project");
        }
    }

    private function getDao()
    {
        return new Dao();
    }

    /**
     * @return FolderUsageRetriever
     */
    private function getFolderUsageRetriever()
    {
        return new FolderUsageRetriever($this->getDao(), TrackerFactory::instance());
    }

    private function getPresenterBuilder()
    {
        $artifact_factory = Tracker_ArtifactFactory::instance();
        $dao              = new NatureDao();

        return new ArtifactPresenterBuilder(
            new Dao(),
            new NatureIsChildLinkRetriever(
                $artifact_factory,
                new Tracker_FormElement_Field_Value_ArtifactLinkDao()
            ),
            $dao,
            $artifact_factory
        );
    }

    public function hide_artifact($params)
    {
        $params['hide_artifact'] = $params['nature'] === NatureInFolderPresenter::NATURE_IN_FOLDER;
    }

    public function event_get_nature_presenter($params)
    {
        if ($params['shortname'] === NatureInFolderPresenter::NATURE_IN_FOLDER) {
            $params['presenter'] = new NatureInFolderPresenter();
        }
    }

    public function process(HTTPRequest $request)
    {
        if (! defined('TRACKER_BASE_URL')) {
            return;
        }

        $router = new Router(
            Tracker_ArtifactFactory::instance(),
            new Tracker_URLVerification(),
            new Controller($this->getPresenterBuilder())
        );

        $router->route($request);
    }

    public function tracker_add_system_natures($params)
    {
        $params['natures'][] = NatureInFolderPresenter::NATURE_IN_FOLDER;
    }

    public function tracker_is_nature_valid($params)
    {
        if ($this->getDao()->isTrackerConfiguredToContainFolders($params['tracker_id']) === false
            && $params['nature'] === NatureInFolderPresenter::NATURE_IN_FOLDER
        ) {
            $params['error'] = "Link between " . $params['artifact']->getId() . " and " . $params['children_id'] . " is inconsistent because tracker " .
                $params['tracker_id'] . " is not defined as a Folder. Artifact " . $params['artifact']->getId() . " added without nature.";
        }
    }

    /** @see Tracker_FormElement_Field_ArtifactLink::PREPEND_ARTIFACTLINK_INFORMATION */
    public function prepend_artifactlink_information($params)
    {
        if ($params['reverse_artifact_links']) {
            return;
        }

        $folder = $this->getFolderOfArtifact($params['artifact'], $params['current_user']);
        if (! $folder) {
            return;
        }

        $current_folder  = $GLOBALS['Language']->getText('plugin_folders', 'current_folder');
        $params['html'] .= '<p>'. $current_folder .' '. $folder->fetchDirectLinkToArtifactWithTitle() .'</p>';
    }

    private function getFolderOfArtifact(Tracker_Artifact $artifact, PFUser $current_user)
    {
        $folder_dao       = new Dao();
        $artifact_factory = Tracker_ArtifactFactory::instance();

        foreach ($folder_dao->searchFoldersTheArtifactBelongsTo($artifact->getId()) as $row) {
            $folder = $artifact_factory->getInstanceFromRow($row);
            if ($folder->userCanView($current_user)) {
                return $folder;
            }
        }

        return null;
    }
}