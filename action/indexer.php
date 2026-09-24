<?php
/**
 * Action module for templater plugin: Handles backlink indexing at page indexing time
 * Backported from YATP.
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\File\PageResolver;

class action_plugin_templater_indexer extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'handle_indexer', ['priority' => 100]);
    }

    public function handle_indexer(Doku_Event $event, $param)
    {
        $pageId = $event->data['page'];
        $text = rawWiki($pageId);

        if (!preg_match_all('/\{\{template>([^#|}]+)(?:\|([^#}]*))?(?:#([^\}]+))?\}\}/', $text, $matches, PREG_SET_ORDER)) {
            return;
        }

        p_set_metadata($pageId, [
            'relation' => [
                'references' => []
            ]
        ], false, true);

        $metadata = p_get_metadata($pageId, 'relation');
        $existingRefs = isset($metadata['references']) ? $metadata['references'] : [];

        $syntax = plugin_load('syntax', 'templater');
        $resolver = new PageResolver(getNS($pageId));
        $defaultNamespace = $syntax->getConf('namespace');

        foreach ($matches as $match) {
            $templateName = $match[1];
            
            if (!empty($defaultNamespace) && !preg_match('/^[:.]/', $templateName)) {
                $templateName = $defaultNamespace . ':' . $templateName;
            }
            
            $templateId = $resolver->resolveId($templateName);
            
            if (!page_exists($templateId)) continue;
            
            $rawFile = io_readfile(wikiFN($templateId));
            if (!$rawFile) continue;

            $rawFile = preg_replace('/<noinclude>.*?<\/noinclude>/is', '', $rawFile);
            $rawFile = preg_replace('/<includeonly>|<\/includeonly>/i', '', $rawFile);

            $replacers = $syntax->_massageReplacers(preg_split('/(?<!\\\\)\|/', $match[2] ?? ''));

            if(!empty($replacers['keys']) && !empty($replacers['vals'])) {
                $rawFile = str_replace($replacers['keys'], $replacers['vals'], $rawFile);
            }
            
            $left_overs = '/'.BEGIN_REPLACE_DELIMITER.'.*'.END_REPLACE_DELIMITER.'/';
            if(!empty($replacers['keys']) && !empty($replacers['vals'])) {
                $def_key = array_search(BEGIN_REPLACE_DELIMITER."DEFAULT_STR".END_REPLACE_DELIMITER, $replacers['keys']);
                $DEFAULT_STR = $def_key !== false ? $replacers['vals'][$def_key] : "";
                $rawFile = preg_replace($left_overs, $DEFAULT_STR, $rawFile);
            }

            $instr = p_get_instructions($rawFile);

            foreach ($instr as $instruction) {
                list($cmd, $params) = $instruction;

                if ($cmd === 'internallink') {
                    $pageIdInInstruction = $params[0];
                    if ($pageIdInInstruction[0] == '.') {
                        $linkID = getNS($templateId).':'.substr($pageIdInInstruction, 1);
                    } else if (strpos($pageIdInInstruction, ':') === false) {
                        $linkID = getNS($templateId).':'.$pageIdInInstruction;
                    } else {
                        $linkID = $pageIdInInstruction;
                    }
                    
                    $linkID = cleanID($linkID);

                    if (!$linkID) continue;

                    $existingRefs[$linkID] = page_exists($linkID);
                }
            }
        }
        
        p_set_metadata($pageId, [
            'relation' => [
                'references' => $existingRefs
            ]
        ], true, true);
        
        if (class_exists('dokuwiki\Search\Indexer')) {
            idx_get_indexer()->addMetaKeys($pageId, 'relation references', $existingRefs);
        } else {
            if (file_exists(DOKU_INC . 'inc/indexer.php')) {
                require_once(DOKU_INC . 'inc/indexer.php');
            }
            idx_get_indexer()->addMetaKeys($pageId, 'relation references', $existingRefs);
        }
    }
}
