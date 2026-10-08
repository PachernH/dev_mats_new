// ข้อมูลตู้คอนเทนเนอร์มาตรฐาน
const containerTypes = {
    '20ft': { length: 589, width: 235, height: 239, maxWeight: 28000, volume: 589 * 235 * 239 },
    '40ft': { length: 1203, width: 235, height: 239, maxWeight: 28000, volume: 1203 * 235 * 239 },
    '40hq': { length: 1203, width: 235, height: 269, maxWeight: 28000, volume: 1203 * 235 * 269 }
};

let items = [];
let currentContainer = containerTypes['20ft'];
let currentScene = null;
let currentRenderer = null;
let currentCamera = null;

// สร้างพาเลทสีสำหรับสินค้า
const COLOR_PALETTE = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
    '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
    '#F8C471', '#82E0AA', '#F1948A', '#85C1E9', '#D7BDE2',
    '#F9E79F', '#ABEBC6', '#EDBB99', '#AED6F1', '#FAD7A0',
    '#76D7C4', '#F5B7B1', '#D2B4DE', '#A9DFBF', '#FDEBD0',
    '#D5DBDB', '#F1948A', '#BB8FCE', '#85C1E9', '#F8C471'
];

let itemTypeColors = new Map(); // เก็บ mapping ระหว่างชื่อสินค้าและสี

// เริ่มต้นระบบ
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    updateItemsCount();
});

function initializeEventListeners() {
    console.log('🔧 กำลังตั้งค่า event listeners...');
    
    // ตรวจสอบ element ก่อนเพิ่ม event listener
    const containerType = document.getElementById('containerType');
    const itemNameInput = document.getElementById('itemName');
    const customContainer = document.getElementById('customContainer');
    
    // เปลี่ยนประเภทตู้
    if (containerType) {
        containerType.addEventListener('change', function(e) {
            if (e.target.value === 'custom') {
                if (customContainer) {
                    customContainer.classList.remove('d-none');
                }
                updateCustomDimensions();
            } else {
                if (customContainer) {
                    customContainer.classList.add('d-none');
                }
                currentContainer = containerTypes[e.target.value];
            }
            updateMaxWeight();
        });
        console.log('✅ ตั้งค่า container type event');
    } else {
        console.warn('⚠️ ไม่พบ element containerType');
    }

    // อัพเดทน้ำหนักสูงสุดเมื่อเปลี่ยนประเภทตู้
    if (containerType) {
        containerType.addEventListener('change', updateMaxWeight);
    }

    // เพิ่มสินค้าด้วยปุ่ม Enter
    if (itemNameInput) {
        itemNameInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') addItem();
        });
        console.log('✅ ตั้งค่า item name event');
    } else {
        console.warn('⚠️ ไม่พบ element itemName');
    }
    
    // ตั้งค่า events สำหรับ CSV (แบบปลอดภัย)
    initializeCSVEvents();
    
    console.log('✅ การตั้งค่า event listeners เสร็จสิ้น');

    // ✅ เพิ่ม event listener สำหรับคลิกขวา
    setupRightClick💾 Save();
    
    console.log('✅ การตั้งค่า event listeners เสร็จสิ้น');
}

function updateCustomDimensions() {
    const type = document.getElementById('containerType').value;
    if (type !== 'custom') return;
    
    document.getElementById('customLength').value = currentContainer.length;
    document.getElementById('customWidth').value = currentContainer.width;
    document.getElementById('customHeight').value = currentContainer.height;
}

function updateMaxWeight() {
    const type = document.getElementById('containerType').value;
    if (type !== 'custom') {
        document.getElementById('maxWeight').value = currentContainer.maxWeight;
    }
}

// เพิ่มสินค้า
function addItem() {
    const length = parseInt(document.getElementById('itemLength').value);
    const width = parseInt(document.getElementById('itemWidth').value);
    const height = parseInt(document.getElementById('itemHeight').value);
    const weight = parseInt(document.getElementById('itemWeight').value);
    const quantity = parseInt(document.getElementById('itemQty').value);
    const name = document.getElementById('itemName').value || `สินค้า ${items.length + 1}`;
    
    // ✅ ตรวจสอบข้อมูลและแปลงหน่วยจาก mm เป็น cm สำหรับการคำนวณ
    if (!length || !width || !height || !weight) {
        showAlert('กรุณากรอกข้อมูลขนาดและน้ำหนักให้ครบถ้วน', 'warning');
        return;
    }

    if (length <= 0 || width <= 0 || height <= 0 || weight <= 0) {
        showAlert('ข้อมูลขนาดและน้ำหนักต้องมากกว่า 0', 'warning');
        return;
    }

    // ✅ แปลงหน่วยจาก mm เป็น cm สำหรับการคำนวณ
    const lengthCm = length / 10;
    const widthCm = width / 10;
    const heightCm = height / 10;

    // กำหนดสีให้กับชนิดสินค้านี้
    // const itemColor = getColorByItemType(name);
    itemColor = getColorByItemType({
        name: name,
        length: lengthCm,
        width: widthCm,
        height: heightCm
    });
    
    for (let i = 0; i < quantity; i++) {
        const itemId = Date.now() + i;
        
        const displayName = name; // ใช้ชื่อปกติ
        
        items.push({
            id: itemId,
            name: displayName,
            originalName: name, // เก็บชื่อเดิมไว้สำหรับการค้นหา
            length: lengthCm,   // ✅ เก็บเป็น cm สำหรับการคำนวณ
            width: widthCm,     // ✅ เก็บเป็น cm สำหรับการคำนวณ
            height: heightCm,   // ✅ เก็บเป็น cm สำหรับการคำนวณ
            weight: weight,
            volume: lengthCm * widthCm * heightCm,
            color: itemColor,
            // ✅ เก็บขนาดเดิมใน mm สำหรับการแสดงผล
            originalLength: length,
            originalWidth: width,
            originalHeight: height,
            // ✅ ข้อมูลเพิ่มเติมสำหรับสินค้าจากฐานข้อมูล
            source: 'manual' // แก้จาก 'database' เป็น 'manual'
        });
    }

    updateItemsList();
    clearItemForm();
    
    showAlert(`เพิ่ม "${name}" จำนวน ${quantity} ชิ้นเรียบร้อยแล้ว`, 'success');
}


function updateItemsList() {
    const itemsList = document.getElementById('itemsList');
    itemsList.innerHTML = '';

    if (items.length === 0) {
        itemsList.innerHTML = `
            <div class="text-center text-muted p-4">
                <i class="fas fa-box-open fa-2x mb-2"></i><br>
                ยังไม่มีสินค้า
            </div>
        `;
        return;
    }

    items.forEach((item, index) => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'item-row';
        
        // ✅ แสดงขนาดเป็น mm และแปลงปริมาตรเป็น m³
        const lengthMm = item.originalLength || (item.length * 10);
        const widthMm = item.originalWidth || (item.width * 10);
        const heightMm = item.originalHeight || (item.height * 10);
        const volumeM3 = (lengthMm * widthMm * heightMm) / 1000000000; // mm³ to m³
        
        itemDiv.innerHTML = `
            <div class="item-info">
                <div class="item-name">${item.name}</div>
                <div class="item-details">
                    ขนาด: ${lengthMm}×${widthMm}×${heightMm} mm | 
                    น้ำหนัก: ${item.weight} kg | 
                    ปริมาตร: ${volumeM3.toFixed(4)} m³
                </div>
            </div>
            <button class="btn btn-danger btn-sm" onclick="removeItem(${index})" title="ลบสินค้า">
                <i class="fas fa-trash"></i>
            </button>
        `;
        itemsList.appendChild(itemDiv);
    });

    updateItemsCount();
}

function removeItem(index) {
    const itemName = items[index].name;
    items.splice(index, 1);
    updateItemsList();
    showAlert(`ลบ "${itemName}" เรียบร้อยแล้ว`, 'info');
}

function clearItemForm() {
    document.getElementById('itemLength').value = '';
    document.getElementById('itemWidth').value = '';
    document.getElementById('itemHeight').value = '';
    document.getElementById('itemWeight').value = '';
    document.getElementById('itemQty').value = '1';
    document.getElementById('itemName').value = '';
    document.getElementById('itemLength').focus();
}

function updateItemsCount() {
    const totalItems = items.length;
    const totalVolume = items.reduce((sum, item) => sum + item.volume, 0);
    const totalWeight = items.reduce((sum, item) => sum + item.weight, 0);
    
    document.getElementById('itemsCountBadge').textContent = totalItems;
    
    // อัพเดทปุ่มคำนวณ
    const calculateBtn = document.getElementById('calculateBtn');
    if (totalItems === 0) {
        calculateBtn.disabled = true;
        calculateBtn.innerHTML = '<i class="fas fa-calculator me-1"></i>กรุณาเพิ่มสินค้า';
    } else {
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = `<i class="fas fa-calculator me-1"></i>คำนวณการจัดเรียง (${totalItems} ชิ้น)`;
    }
}

// ฟังก์ชันนำเงื่อนไขไปใช้กับสินค้า
function applyLoadingConditions(items, conditions) {
    let processedItems = [...items];
    
    console.log('📏 กำลังนำเงื่อนไขไปใช้กับสินค้า:', {
        itemSpacing: conditions.itemSpacing,
        allowRotation: conditions.allowRotation,
        groupingMethod: conditions.groupingMethod
    });
    
    // จัดเรียงตามเงื่อนไข
    processedItems = sortItemsByConditions(processedItems, conditions);
    
    // ✅ เพิ่มระยะห่างระหว่างสินค้าเท่านั้น (ไม่รวมผนัง container)
    if (conditions.itemSpacing > 0) {
        console.log(`📏 เพิ่มระยะห่างระหว่างสินค้า ${conditions.itemSpacing} cm`);
        
        // ✅ เก็บข้อมูลระยะห่างไว้ใช้ในการคำนวณ แต่ไม่เปลี่ยนขนาดสินค้า
        processedItems = processedItems.map(item => {
            return {
                ...item,
                // ✅ เก็บข้อมูลระยะห่างแยกต่างหาก ไม่ได้รวมในขนาดสินค้า
                spacing: conditions.itemSpacing,
                // ✅ เก็บขนาดเดิมไว้ (ไม่เปลี่ยนแปลงขนาดสินค้า)
                originalLength: item.originalLength || item.length,
                originalWidth: item.originalWidth || item.width,
                originalHeight: item.originalHeight || item.height
            };
        });
    }
    
    // จัดกลุ่มสินค้าตามเงื่อนไข
    if (conditions.groupingMethod !== 'none') {
        processedItems = groupItems(processedItems, conditions.groupingMethod);
    }
    
    return processedItems;
}

// ฟังก์ชันจัดกลุ่มสินค้า
function groupItems(items, groupingMethod) {
    const groupedItems = [...items];
    
    switch (groupingMethod) {
        case 'sameType':
            // กลุ่มตามชื่อสินค้า
            groupedItems.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'weightClass':
            // กลุ่มตามระดับน้ำหนัก
            groupedItems.sort((a, b) => {
                const weightClassA = getWeightClass(a.weight);
                const weightClassB = getWeightClass(b.weight);
                return weightClassA - weightClassB;
            });
            break;
        case 'destination':
            // กลุ่มตามจุดหมาย (ในที่นี้ใช้ชื่อสินค้าแทน)
            groupedItems.sort((a, b) => a.name.localeCompare(b.name));
            break;
    }
    
    return groupedItems;
}

// ฟังก์ชันกำหนดระดับน้ำหนัก
function getWeightClass(weight) {
    if (weight <= 10) return 1;      // น้ำหนักเบา
    else if (weight <= 25) return 2; // น้ำหนักปานกลาง
    else return 3;                   // น้ำหนักมาก
}


// ในฟังก์ชัน displayResults() ให้แก้ไขเป็นดังนี้
function displayResults(results, containerData) {
    console.log('📊 แสดงผลลัพธ์การจัดเรียง');
    
    if (!results || !results.plan) {
        showAlert('❌ ไม่มีผลลัพธ์จากการคำนวณ', 'danger');
        return;
    }

    // ✅ กรองเฉพาะสินค้าที่มีตำแหน่งจริง
    const validPlan = results.plan.filter(item => 
        item.position && 
        typeof item.position.x === 'number' && 
        typeof item.position.y === 'number' && 
        typeof item.position.z === 'number'
    );

    console.log(`✅ สินค้าที่มีตำแหน่งถูกต้อง: ${validPlan.length}/${results.plan.length} ชิ้น`);

    if (validPlan.length === 0) {
        showAlert('❌ ไม่มีสินค้าที่มีตำแหน่งการจัดเรียงที่ถูกต้อง', 'danger');
        return;
    }

        // ✅ ตรวจสอบตำแหน่ง Z
    const zPositionIssues = validateZPositions(validPlan);
    if (zPositionIssues.length > 0) {
        console.warn('❌ พบปัญหาการวางตำแหน่ง:', zPositionIssues);
        showAlert(`⚠️ พบสินค้า ${zPositionIssues.length} ชิ้นที่อาจลอยอยู่ในอากาศ`, 'warning');
    }

    // ✅ คำนวณจำนวนชั้นที่ใช้
    const layersUsed = calculateLayersUsed(validPlan);
    console.log('🏗️ จำนวนชั้นที่ใช้:', layersUsed);

    // ✅ บันทึกข้อมูลแผนปัจจุบันและเงื่อนไข
    window.currentPlan = validPlan;
    window.currentConditions = results.conditions;
    window.currentContainer = containerData;
    
    console.log('💾 บันทึกข้อมูลสำหรับมุมมอง:', {
        planCount: window.currentPlan.length,
        container: window.currentContainer,
        layersUsed: layersUsed
    });

    // ✅ ล้าง visualization container
    const containerElem = document.getElementById('visualization');
    containerElem.innerHTML = '';

    // ✅ สร้าง 3D visualization ก่อนและแสดงเป็นค่าเริ่มต้น
    try {
        const visualizationResult = create3DVisualization(validPlan, containerData, results.conditions);
        if (visualizationResult) {
            const { scene, camera, renderer } = visualizationResult;
            window.currentScene = scene;
            window.currentCamera = camera;
            window.currentRenderer = renderer;
            
            // ✅ แสดง 3D เป็นค่าเริ่มต้น
            if (renderer && renderer.domElement) {
                renderer.domElement.style.display = 'block';
            }
            console.log('✅ สร้างและแสดง 3D visualization เป็นค่าเริ่มต้น');
            
            // ✅ อัพเดทปุ่มเป็นสถานะ 3D
            updateToggleViewButton(true);
        }
    } catch (error) {
        console.error('❌ เกิดข้อผิดพลาดในการสร้าง 3D:', error);
        showAlert('⚠️ ไม่สามารถสร้างมุมมอง 3D ได้ กำลังแสดงมุมมอง 2D แทน', 'warning');
        createSimple2DView(validPlan, containerData);
    }

    // อัพเดทสถิติ
//    const efficiencyDetails = calculateBaseEfficiency(validPlan, containerData, items.length);
//    document.getElementById('spaceUsed').textContent = efficiencyDetails.area + '%';
//    document.getElementById('weightUsed').textContent = efficiencyDetails.weight + '%';
//    document.getElementById('itemsCount').textContent = `${results.plan.length}/${items.length}`;
//    document.getElementById('efficiency').textContent = efficiencyDetails.overall + '%';

    const efficiencyDetails = calculateEfficiency(validPlan, containerData);
    document.getElementById('spaceUsed').textContent = efficiencyDetails.volume + '%';
    document.getElementById('weightUsed').textContent = efficiencyDetails.weight + '%';
    document.getElementById('efficiency').textContent = efficiencyDetails.overall + '%';

    // แสดง statistics
    document.getElementById('statistics').style.display = 'flex';
    
    // ล้างเนื้อหาเก่าก่อนแสดงใหม่
    clearPreviousResults();
    
    // แสดงข้อมูลเงื่อนไขที่ใช้
    showBaseConditionsSummary(results.conditions);
    
    // ✅ แสดงสรุปการจัดเรียงพร้อมข้อมูลชั้น
    //showPackingSummaryWithLayers(results.plan.length, items.length, results.totalWeight, containerData.maxWeight, layersUsed);
    
    // แสดงแผนการจัดเรียง
    updateLoadingPlanTable(results.plan);

    // แสดงสินค้าที่จัดไม่ได้แบบย่อ
    showUnpackedItems(results.plan, items.length);

    // ในฟังก์ชัน displayResults
    validateColorConsistency(validPlan);
    
    showAlert(`✅ คำนวณการจัดเรียงสำเร็จ! จัดได้ ${validPlan.length}/${items.length} ชิ้น ใน ${layersUsed} ชั้น`, 'success');
}

function validateColorConsistency(items) {
    const colorMap = new Map();
    items.forEach(item => {
        const baseSize = `${Math.round(item.width)}x${Math.round(item.length)}`;
        const expectedColor = getColorByItemType(item);
        
        if (item.color && item.color !== expectedColor) {
            console.warn(`⚠️ สีไม่ตรง: ${item.name}`, {
                สีปัจจุบัน: item.color,
                สีที่ควรเป็น: expectedColor,
                ขนาดฐาน: baseSize
            });
        }
    });
}

function create3DVisualization(itemsToVisualize, containerData, conditions = {}) {
    console.log('🎯 เริ่มสร้าง 3D Visualization พร้อมชื่อสินค้ารอบตัว');
    
    try {
        // ตรวจสอบ dependencies
        checkDependencies();
        
        const containerElem = document.getElementById('visualization');
        if (!containerElem) {
            throw new Error('ไม่พบ element visualization');
        }
        
        // ล้าง container ให้สะอาดก่อนสร้างใหม่
        containerElem.innerHTML = '';
        
        // สร้าง scene พื้นฐาน
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0xf0f8ff);
        
        // สร้าง camera
        const camera = new THREE.PerspectiveCamera(
            60, 
            containerElem.offsetWidth / containerElem.offsetHeight, 
            0.1, 
            2000
        );
        
        // สเกลสำหรับแสดงผล
        const scale = 0.01;
        
        console.log('📐 ขนาดตู้:', containerData);

        // สร้างตู้คอนเทนเนอร์
        const containerGeometry = new THREE.BoxGeometry(
            containerData.length * scale,
            containerData.height * scale,
            containerData.width * scale
        );
        
        const containerEdges = new THREE.EdgesGeometry(containerGeometry);
        const containerLineMaterial = new THREE.LineBasicMaterial({ 
            color: 0x34495e,
            linewidth: 2
        });
        const containerWireframe = new THREE.LineSegments(containerEdges, containerLineMaterial);
        containerWireframe.position.set(
            (containerData.length * scale) / 2,
            (containerData.height * scale) / 2,
            (containerData.width * scale) / 2
        );
        scene.add(containerWireframe);
        
        // ✅ สร้าง renderer
        const renderer = new THREE.WebGLRenderer({ 
            antialias: true,
            alpha: true
        });
        renderer.setSize(containerElem.offsetWidth, containerElem.offsetHeight);
        renderer.setClearColor(0xf0f8ff, 1);
        containerElem.appendChild(renderer.domElement);
        
        // ✅ เพิ่มแสงพื้นฐาน
        const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
        scene.add(ambientLight);
        
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(5, 10, 7);
        scene.add(directionalLight);

        // ✅ เพิ่มสินค้า
        if (itemsToVisualize && itemsToVisualize.length > 0) {
            console.log('📦 เริ่มเพิ่มสินค้า:', itemsToVisualize.length, 'ชิ้น');
            
            let addedItems = 0;
            
            for (let i = 0; i < itemsToVisualize.length; i++) {
                const currentItem = itemsToVisualize[i];
                
                if (!currentItem.position) {
                    console.log('❌ สินค้าไม่มีตำแหน่ง:', currentItem.name);
                    continue;
                }

                try {
                    // กำหนดสี
                    let itemColor;
                    if (currentItem.baseGroup) {
                        itemColor = getColorForBaseGroup(currentItem.baseGroup);
                    } else if (currentItem.color) {
                        itemColor = currentItem.color;
                    } else {
                        itemColor = '#3498db';
                    }
                    
                    // ✅ คำนวณขนาด - แก้ไขการใช้ currentItem แทน item
                    const itemLength = (currentItem.actualSize?.length || currentItem.length) * scale;
                    const itemWidth = (currentItem.actualSize?.width || currentItem.width) * scale;
                    const itemHeight = (currentItem.actualSize?.height || currentItem.height) * scale; // ✅ แก้ไขตรงนี้
                    
                    // สร้าง geometry
                    const geometry = new THREE.BoxGeometry(itemLength, itemHeight, itemWidth);
                    
                    // ✅ ทำให้กล่องโปร่งใสมากขึ้นเพื่อเห็น label ชัดเจน
                    const material = new THREE.MeshPhongMaterial({ 
                        color: new THREE.Color(itemColor),
                        transparent: true,
                        opacity: 0.7,
                        specular: 0x222222,
                        shininess: 20
                    });
                    
                    const cube = new THREE.Mesh(geometry, material);
                    
                    // คำนวณตำแหน่ง
                    const posX = (currentItem.position.x * scale) + (itemLength / 2);
                    const posY = (currentItem.position.z * scale) + (itemHeight / 2);
                    const posZ = (currentItem.position.y * scale) + (itemWidth / 2);
                    
                    cube.position.set(posX, posY, posZ);
                    
                    // ✅ เพิ่มชื่อสินค้ารอบตัว (ใช้ฟังก์ชัน optimized)
                    addOptimizedItemLabel(cube, currentItem, scale);
                    
                    // เก็บข้อมูลพื้นฐาน
                    cube.userData = {
                        isItem: true,
                        itemData: currentItem
                    };
                    
                    // เพิ่มขอบ
                    const edges = new THREE.EdgesGeometry(geometry);
                    const edgeMaterial = new THREE.LineBasicMaterial({ color: 0x000000 });
                    const wireframe = new THREE.LineSegments(edges, edgeMaterial);
                    cube.add(wireframe);
                    
                    scene.add(cube);
                    addedItems++;
                    
                    console.log(`✅ เพิ่มสินค้า ${currentItem.name} (${i + 1}/${itemsToVisualize.length})`);
                    
                } catch (itemError) {
                    console.error(`❌ ไม่สามารถเพิ่มสินค้า ${currentItem.name}:`, itemError);
                }
            }
            
            console.log(`✅ เพิ่มสินค้าเรียบร้อย: ${addedItems}/${itemsToVisualize.length} ชิ้น`);
        } else {
            console.warn('⚠️ ไม่มีสินค้าที่จะแสดง');
        }
        
        // ✅ ตั้งค่า camera
        const centerX = (containerData.length * scale) / 2;
        const centerY = (containerData.height * scale) / 2;
        const centerZ = (containerData.width * scale) / 2;
        
        camera.position.set(
            containerData.length * scale * 1.5,
            containerData.height * scale * 1.2,
            containerData.width * scale * 1.5
        );
        camera.lookAt(centerX, centerY, centerZ);
        
        // ✅ ตั้งค่า camera controls
        setupCameraControls(camera, renderer, scene, containerElem, containerData, scale);
        
        // ✅ Render ครั้งแรก
        renderer.render(scene, camera);
        
        // ✅ เก็บ reference ไว้ใน window object
        window.currentScene = scene;
        window.currentCamera = camera;
        window.currentRenderer = renderer;
        window.currentContainer = containerData;
        window.currentPlan = itemsToVisualize;
        
        console.log('✅ สร้าง 3D Visualization สำเร็จ');
        
        return { scene, camera, renderer };
        
    } catch (error) {
        console.error('❌ เกิดข้อผิดพลาดในการสร้าง 3D Visualization:', error);
        
        const containerElem = document.getElementById('visualization');
        if (containerElem) {
            containerElem.innerHTML = `
                <div class="text-center p-5 text-danger">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h5>ไม่สามารถโหลด 3D Visualization ได้</h5>
                    <p class="small">${error.message}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-redo me-1"></i>รีเฟรชหน้า
                    </button>
                </div>
            `;
        }
        
        throw error;
    }
}

function checkDependencies() {
    if (typeof THREE === 'undefined') {
        throw new Error('Three.js ไม่ได้โหลด');
    }
    if (typeof getColorByItemType === 'undefined') {
        console.warn('⚠️ getColorByItemType ไม่พร้อมใช้งาน จะใช้สีสุ่มแทน');
    }
}

// เพิ่มฟังก์ชัน setupCameraControls ที่อัพเดทแล้ว
function setupCameraControls(camera, renderer, scene, containerElem, container, scale) {
    console.log('🎮 ตั้งค่า Camera Controls แบบง่าย');
    
    let isMouseDown = false;
    let previousMousePosition = { x: 0, y: 0 };
    
    const canvas = renderer.domElement;
    const centerX = (container.length * scale) / 2;
    const centerY = (container.height * scale) / 2;
    const centerZ = (container.width * scale) / 2;
    
    function updateRender() {
        renderer.render(scene, camera);
    }
    
    // Mouse controls แบบง่าย
    canvas.addEventListener('mousedown', function(e) {
        isMouseDown = true;
        previousMousePosition = { x: e.clientX, y: e.clientY };
        canvas.style.cursor = 'grabbing';
    });
    
    canvas.addEventListener('mouseup', function() {
        isMouseDown = false;
        canvas.style.cursor = 'grab';
    });
    
    canvas.addEventListener('mousemove', function(e) {
        if (!isMouseDown) return;
        
        const deltaMove = {
            x: e.clientX - previousMousePosition.x,
            y: e.clientY - previousMousePosition.y
        };
        
        // หมุน camera รอบจุดศูนย์กลาง
        const radius = Math.sqrt(
            Math.pow(camera.position.x - centerX, 2) +
            Math.pow(camera.position.y - centerY, 2) +
            Math.pow(camera.position.z - centerZ, 2)
        );
        
        const theta = Math.atan2(camera.position.x - centerX, camera.position.z - centerZ);
        const phi = Math.acos((camera.position.y - centerY) / radius);
        
        const newTheta = theta + deltaMove.x * 0.01;
        const newPhi = Math.max(0.1, Math.min(Math.PI - 0.1, phi + deltaMove.y * 0.01));
        
        camera.position.x = centerX + radius * Math.sin(newPhi) * Math.sin(newTheta);
        camera.position.y = centerY + radius * Math.cos(newPhi);
        camera.position.z = centerZ + radius * Math.sin(newPhi) * Math.cos(newTheta);
        
        camera.lookAt(centerX, centerY, centerZ);
        updateRender();
        
        previousMousePosition = { x: e.clientX, y: e.clientY };
    });
    
    // Zoom with mouse wheel
    canvas.addEventListener('wheel', function(e) {
        e.preventDefault();
        
        const zoomSpeed = 0.001;
        const zoomDelta = -e.deltaY * zoomSpeed;
        
        const direction = new THREE.Vector3();
        direction.subVectors(camera.position, new THREE.Vector3(centerX, centerY, centerZ)).normalize();
        
        const newPosition = new THREE.Vector3().copy(camera.position).add(direction.multiplyScalar(zoomDelta * 50));
        
        const distance = newPosition.distanceTo(new THREE.Vector3(centerX, centerY, centerZ));
        const minDistance = Math.max(container.length, container.width, container.height) * scale * 0.3;
        const maxDistance = Math.max(container.length, container.width, container.height) * scale * 10;
        
        if (distance >= minDistance && distance <= maxDistance) {
            camera.position.copy(newPosition);
        }
        
        camera.lookAt(centerX, centerY, centerZ);
        updateRender();
    });
    
    canvas.style.cursor = 'grab';
    
    console.log('✅ ตั้งค่า Camera Controls สำเร็จ');
}

function addOptimizedItemLabel(cube, item, scale) {
    if (!item.name) return;
    
    try {
        // ✅ คำนวณขนาดสินค้า
        const itemLength = (item.actualSize?.length || item.length) * scale;
        const itemWidth = (item.actualSize?.width || item.width) * scale;
        const itemHeight = (item.actualSize?.height || item.height) * scale;
        
        // ✅ กำหนดขนาดสูงสุดสำหรับ label
        const maxLabelWidth = itemLength * 0.8;
        const maxLabelHeight = itemHeight * 0.3;
        
        // ✅ สร้างข้อความ 3D
        const textMesh = create3DText(item.name, maxLabelWidth, maxLabelHeight, 0x000000, 0xffffff);
        
        // ✅ เลือกเฉพาะ 3 ด้านที่สำคัญ
        const labels = [
            // ด้านหน้า
            {
                position: [0, itemHeight * 0.1, itemWidth / 2 + 0.001],
                rotation: [0, 0, 0],
                face: 'front'
            },
            // ด้านขวา
            {
                position: [itemLength / 2 + 0.001, itemHeight * 0.1, 0],
                rotation: [0, Math.PI / 2, 0],
                face: 'right'
            },
            // ด้านบน (ถ้าสูงพอ)
            ...(itemHeight > 0.15 ? [{
                position: [0, itemHeight / 2 + 0.001, 0],
                rotation: [-Math.PI / 2, 0, 0],
                face: 'top'
            }] : [])
        ];
        
        // ✅ เพิ่ม labels
        labels.forEach((labelConfig, index) => {
            const label = textMesh.clone();
            label.position.set(...labelConfig.position);
            label.rotation.set(...labelConfig.rotation);
            label.userData = { 
                isLabel: true, 
                face: labelConfig.face,
                itemName: item.name
            };
            cube.add(label);
        });
        
        console.log(`✅ เพิ่มชื่อสินค้า "${item.name}" (${labels.length} ด้าน)`);
        
    } catch (error) {
        console.error(`❌ ไม่สามารถเพิ่มชื่อสินค้า ${item.name}:`, error);
    }
}

// ✅ ฟังก์ชันสร้างข้อความ 3D
function create3DText(text, maxWidth, maxHeight, textColor = 0x000000, backgroundColor = 0xffffff) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    
    // ✅ คำนวณขนาด canvas ตามขนาดสินค้า
    const pixelWidth = Math.floor(maxWidth * 200);
    const pixelHeight = Math.floor(maxHeight * 200);
    
    // ✅ กำหนดขนาดขั้นต่ำและสูงสุด
    const minWidth = 60;
    const minHeight = 30;
    const maxCanvasWidth = 200;
    const maxCanvasHeight = 80;
    
    canvas.width = Math.max(minWidth, Math.min(maxCanvasWidth, pixelWidth));
    canvas.height = Math.max(minHeight, Math.min(maxCanvasHeight, pixelHeight));
    
    const padding = 4;
    const availableWidth = canvas.width - (padding * 2);
    const availableHeight = canvas.height - (padding * 2);
    
    // ✅ คำนวณขนาดฟอนต์ให้พอดีกับพื้นที่
    let fontSize = Math.floor(availableHeight * 0.6);
    context.font = `Bold ${fontSize}px Arial`;
    
    // ✅ ตรวจสอบความกว้างของข้อความ
    let textWidth = context.measureText(text).width;
    
    // ✅ ลดขนาดฟอนต์จนกว่าข้อความจะพอดี
    while (textWidth > availableWidth && fontSize > 8) {
        fontSize -= 1;
        context.font = `Bold ${fontSize}px Arial`;
        textWidth = context.measureText(text).width;
    }
    
    // ✅ ตัดข้อความถ้ายังไม่พอดี
    let displayText = text;
    if (textWidth > availableWidth && fontSize <= 8) {
        const ellipsis = '...';
        const ellipsisWidth = context.measureText(ellipsis).width;
        let maxChars = 0;
        
        for (let i = 1; i <= text.length; i++) {
            const testText = text.substring(0, i) + ellipsis;
            if (context.measureText(testText).width <= availableWidth - ellipsisWidth) {
                maxChars = i;
            } else {
                break;
            }
        }
        
        if (maxChars > 0) {
            displayText = text.substring(0, maxChars) + ellipsis;
        } else {
            displayText = text.substring(0, 3) + ellipsis;
        }
    }
    
    // ✅ วาดพื้นหลังสีขาว
    context.fillStyle = `#${backgroundColor.toString(16).padStart(6, '0')}`;
    context.fillRect(0, 0, canvas.width, canvas.height);
    
    // ✅ วาดขอบสีเทาเบาๆ
    context.strokeStyle = '#e0e0e0';
    context.lineWidth = 1;
    context.strokeRect(0, 0, canvas.width, canvas.height);
    
    // ✅ วาดข้อความสีดำ
    context.fillStyle = `#${textColor.toString(16).padStart(6, '0')}`;
    context.font = `Bold ${fontSize}px Arial`;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(displayText, canvas.width / 2, canvas.height / 2);
    
    // ✅ สร้าง texture จาก canvas
    const texture = new THREE.CanvasTexture(canvas);
    texture.minFilter = THREE.LinearFilter;
    texture.magFilter = THREE.LinearFilter;
    
    // ✅ สร้าง material และ plane geometry
    const material = new THREE.MeshBasicMaterial({
        map: texture,
        transparent: true,
        opacity: 0.95,
        side: THREE.DoubleSide
    });
    
    // ✅ ขนาด plane ให้พอดีกับ canvas
    const planeWidth = canvas.width / 200;
    const planeHeight = canvas.height / 200;
    const geometry = new THREE.PlaneGeometry(planeWidth, planeHeight);
    
    const textMesh = new THREE.Mesh(geometry, material);
    return textMesh;
}

// ฟังก์ชันมุมมอง - ประกาศเป็น global ทันที
function setViewPreset(viewType) {
    console.log('📐 เปลี่ยนมุมมองเป็น:', viewType);
    
    if (!window.currentCamera || !window.currentScene || !window.currentRenderer) {
        console.log('❌ Camera, Scene หรือ Renderer ยังไม่พร้อม');
        showAlert('❌ 3D Visualization ยังไม่พร้อม', 'warning');
        return;
    }
    
    const container = getContainerData();
    const scale = 0.01;
    const centerX = (container.length * scale) / 2;
    const centerY = (container.height * scale) / 2;
    const centerZ = (container.width * scale) / 2;
    
    // คำนวณระยะห่างจากศูนย์กลาง
    const baseDistance = Math.max(container.length, container.width, container.height) * scale * 1.5;
    
    switch(viewType) {
        case 'top':
            // มุมมองด้านบน
            window.currentCamera.position.set(centerX, baseDistance * 2, centerZ);
            window.currentCamera.lookAt(centerX, centerY, centerZ);
            break;
        case 'front':
            // มุมมองด้านหน้า
            window.currentCamera.position.set(centerX, centerY, baseDistance * 2);
            window.currentCamera.lookAt(centerX, centerY, centerZ);
            break;
        case 'side':
            // มุมมองด้านข้าง
            window.currentCamera.position.set(baseDistance * 2, centerY, centerZ);
            window.currentCamera.lookAt(centerX, centerY, centerZ);
            break;
        case 'perspective':
            // มุมมอง perspective
            window.currentCamera.position.set(
                container.length * scale * 1.5,
                container.height * scale * 1.2,
                container.width * scale * 1.5
            );
            window.currentCamera.lookAt(centerX, centerY, centerZ);
            break;
        default:
            console.warn('⚠️ มุมมองไม่รู้จัก:', viewType);
            return;
    }
    
    // อัพเดทการเรนเดอร์
    if (window.currentRenderer) {
        window.currentRenderer.render(window.currentScene, window.currentCamera);
    }
    
    console.log('✅ เปลี่ยนมุมมองสำเร็จ:', viewType);
    showAlert(`✅ เปลี่ยนเป็นมุมมอง ${viewType}`, 'success');
}


// ✅ แก้ไขฟังก์ชันแสดงสรุปการจัดเรียงให้รวมข้อมูลชั้น
function showPackingSummaryWithLayers(packedCount, totalCount, totalWeight, maxWeight, layersUsed) {
    const packedPercentage = ((packedCount / totalCount) * 100).toFixed(1);
    const weightPercentage = ((totalWeight / maxWeight) * 100).toFixed(1);
    
    const summaryHTML = `
        <div class="alert alert-success mt-3 packing-summary">
            <h6><i class="fas fa-check-circle me-2"></i>สรุปผลการจัดเรียง</h6>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>จำนวนสินค้าที่จัดเรียงได้:</strong>
                        <span class="badge bg-success fs-6">${packedCount} / ${totalCount} ชิ้น (${packedPercentage}%)</span>
                    </div>
                    <div class="progress mb-3" style="height: 12px;">
                        <div class="progress-bar bg-success" style="width: ${packedPercentage}%"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>น้ำหนักที่ใช้:</strong>
                        <span class="badge bg-info fs-6">${totalWeight} / ${maxWeight} kg (${weightPercentage}%)</span>
                    </div>
                    <div class="progress mb-3" style="height: 12px;">
                        <div class="progress-bar bg-info" style="width: ${weightPercentage}%"></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>จำนวนชั้นที่ใช้:</strong>
                        <span class="badge bg-warning fs-6">${layersUsed} ชั้น</span>
                    </div>
                    <div class="progress mb-3" style="height: 12px;">
                        <div class="progress-bar bg-warning" style="width: ${(layersUsed / 10) * 100}%"></div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // แทรกหลังจากเงื่อนไข
    const loadingPlanCard = document.querySelector('.card:has(#loadingPlan)');
    const conditionsElement = loadingPlanCard.querySelector('.conditions-summary');
    if (conditionsElement) {
        conditionsElement.insertAdjacentHTML('afterend', summaryHTML);
    } else {
        // ถ้าไม่มีเงื่อนไข ให้แทรกที่จุดเริ่มต้น
        loadingPlanCard.querySelector('.card-body').insertAdjacentHTML('afterbegin', summaryHTML);
    }
}


// ✅ เพิ่มฟังก์ชันล้างผลลัพธ์เก่า
function clearPreviousResults() {
    const loadingPlanCard = document.querySelector('.card:has(#loadingPlan)');
    
    // ลบ element ที่อาจแสดงซ้ำ
    const elementsToRemove = loadingPlanCard.querySelectorAll(
        '.packing-summary, .conditions-summary, .unpacked-items-alert'
    );
    elementsToRemove.forEach(element => element.remove());
}

// ✅ เพิ่มฟังก์ชันคำนวณประสิทธิภาพ
function calculateEfficiency(plan, container) {
    if (!plan || plan.length === 0) {
        return { volume: 0, weight: 0, overall: 0 };
    }

    // คำนวณปริมาตรที่ใช้
    let usedVolume = 0;
    plan.forEach(item => {
        const itemLength = item.actualSize?.length || item.length;
        const itemWidth = item.actualSize?.width || item.width;
        const itemHeight = item.actualSize?.height || item.height;
        usedVolume += itemLength * itemWidth * itemHeight;
    });

    const containerVolume = container.length * container.width * container.height;
    const volumeEfficiency = ((usedVolume / containerVolume) * 100);

    // คำนวณน้ำหนักที่ใช้
    const totalWeight = plan.reduce((sum, item) => sum + item.weight, 0);
    const weightEfficiency = ((totalWeight / container.maxWeight) * 100);

    // คำนวณประสิทธิภาพรวม
    const overallEfficiency = (volumeEfficiency + weightEfficiency) / 2;

    return {
        volume: volumeEfficiency.toFixed(1),
        weight: weightEfficiency.toFixed(1),
        overall: overallEfficiency.toFixed(1)
    };
}

// ฟังก์ชันช่วยเหลือสำหรับแสดงข้อความเงื่อนไข
function getSortingOrderText(order) {
    const orders = {
        'area': 'พื้นที่ฐานมากไปน้อย (ยาว×กว้าง)',
        'weight': 'น้ำหนักมากไปน้อย', 
        'length': 'ความยาวมากไปน้อย',
        'width': 'ความกว้างมากไปน้อย',
        'random': 'แบบสุ่ม'
    };
    return orders[order] || order;
}

function getGroupingMethodText(method) {
    const methods = {
        'none': 'ไม่จัดกลุ่ม',
        'sameType': 'ตามประเภทสินค้า',
        'destination': 'ตามจุดหมาย',
        'weightClass': 'ตามระดับน้ำหนัก'
    };
    return methods[method] || method;
}

// ✅ แก้ไขฟังก์ชัน updateLoadingPlanTable ให้จัดการกับส่วนหัวตารางให้ถูกต้อง
function updateLoadingPlanTable(plan) {
    const tbody = document.getElementById('loadingPlan');
    tbody.innerHTML = '';

    if (plan.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-muted p-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ไม่สามารถจัดเรียงสินค้าได้
                </td>
            </tr>
        `;
        return;
    }

    // ✅ คำนวณชั้นที่ถูกต้อง
    const itemsWithLayers = plan.map(item => {
        const layer = calculateExactLayer(item, plan);
        return {
            ...item,
            layer: layer
        };
    });

    itemsWithLayers.forEach((item, index) => {
        const row = document.createElement('tr');
        
        // ✅ แปลงหน่วยจาก cm เป็น mm สำหรับการแสดงผล
        const size = item.actualSize ? 
            `${(item.actualSize.length * 10)}×${(item.actualSize.width * 10)}×${(item.actualSize.height * 10)} mm` :
            `${(item.length * 10)}×${(item.width * 10)}×${(item.height * 10)} mm`;
        
        // ✅ กำหนดไอคอนและข้อความสำหรับชั้นที่จัดเรียง
        const layerIcon = getLayerIcon(item.layer);
        const layerText = `ชั้นที่ ${item.layer}`;
        const layerColor = getLayerColor(item.layer);
        
        // ✅ กำหนดข้อมูลผนัง
        const wallInfo = item.wallSide === 'left' ? 
            '<span class="badge bg-primary">ผนังซ้าย</span>' :
            item.wallSide === 'right' ?
            '<span class="badge bg-info">ผนังขวา</span>' :
            '<span class="badge bg-secondary">กลาง</span>';
            
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${item.name}</td>
            <td>${item.position.x * 10} mm</td>
            <td>${item.position.y * 10} mm</td>
            <td>${item.position.z * 10} mm</td>
            <td>${size}</td>
            <td>${item.weight} kg</td>
            <td>
                <span class="badge" style="background-color: ${layerColor}; color: white;">
                    ${layerIcon} ${layerText}
                </span>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// ✅ เพิ่มฟังก์ชันคำนวณชั้นที่ถูกต้อง
function calculateExactLayer(currentItem, allItems) {
    if (currentItem.position.z === 0) return 1; // ชั้นล่างสุด
    
    // ✅ หาสินค้าทั้งหมดที่อยู่ใต้สินค้านี้
    const itemsBelow = allItems.filter(item => {
        if (item.id === currentItem.id) return false;
        
        const currentLength = currentItem.actualSize?.length || currentItem.length;
        const currentWidth = currentItem.actualSize?.width || currentItem.width;
        const itemLength = item.actualSize?.length || item.length;
        const itemWidth = item.actualSize?.width || item.width;
        
        // ✅ ตรวจสอบว่าสินค้านี้อยู่ใต้สินค้าปัจจุบัน
        const isBelowX = item.position.x < currentItem.position.x + currentLength && 
                        item.position.x + itemLength > currentItem.position.x;
        const isBelowY = item.position.y < currentItem.position.y + currentWidth && 
                        item.position.y + itemWidth > currentItem.position.y;
        const isBelowZ = item.position.z + (item.actualSize?.height || item.height) <= currentItem.position.z;
        
        return isBelowX && isBelowY && isBelowZ;
    });
    
    if (itemsBelow.length === 0) {
        // ✅ ถ้าไม่มีสินค้าอยู่ใต้เลย แสดงว่าอยู่ชั้นที่ 1
        return 1;
    }
    
    // ✅ หาชั้นสูงสุดของสินค้าที่อยู่ใต้ + 1
    const maxLayerBelow = Math.max(...itemsBelow.map(item => {
        const layer = calculateExactLayer(item, allItems);
        return layer;
    }));
    
    return maxLayerBelow + 1;
}

// ✅ ฟังก์ชันกำหนดไอคอนตามชั้นที่จัดเรียง
function getLayerIcon(layer) {
    switch(layer) {
        case 1:
            return '🟢'; // ชั้นล่างสุด - สีเขียว
        case 2:
            return '🟡'; // ชั้นที่ 2 - สีเหลือง
        case 3:
            return '🟠'; // ชั้นที่ 3 - สีส้ม
        case 4:
            return '🔴'; // ชั้นที่ 4 - สีแดง
        case 5:
            return '🟣'; // ชั้นที่ 5 - สีม่วง
        default:
            return '🔵'; // ชั้นอื่นๆ - สีน้ำเงิน
    }
}

// ✅ ฟังก์ชันกำหนดสีตามชั้นที่จัดเรียง
function getLayerColor(layer) {
    switch(layer) {
        case 1:
            return '#28a745'; // ชั้นล่างสุด - สีเขียว
        case 2:
            return '#ffc107'; // ชั้นที่ 2 - สีเหลือง
        case 3:
            return '#fd7e14'; // ชั้นที่ 3 - สีส้ม
        case 4:
            return '#dc3545'; // ชั้นที่ 4 - สีแดง
        case 5:
            return '#6f42c1'; // ชั้นที่ 5 - สีม่วง
        default:
            return '#007bff'; // ชั้นอื่นๆ - สีน้ำเงิน
    }
}

// แสดง alert
function showAlert(message, type = 'info') {
    // ลบ alert เก่าทั้งหมดก่อน
    const existingAlerts = document.querySelectorAll('.custom-alert');
    existingAlerts.forEach(alert => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    });
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `custom-alert alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 80px; /* ✅ ย้ายลงมาจากด้านบนให้พ้นปุ่มควบคุม */
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
        border: none;
    `;
    
    // กำหนดไอคอนตามประเภท
    let icon = 'info-circle';
    switch(type) {
        case 'success':
            icon = 'check-circle';
            break;
        case 'warning':
            icon = 'exclamation-triangle';
            break;
        case 'danger':
            icon = 'times-circle';
            break;
        case 'info':
        default:
            icon = 'info-circle';
    }
    
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${icon} me-2"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // ลบ alert อัตโนมัติหลังจาก 4 วินาที
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 4000);
}

// Export ข้อมูล
function exportToExcel() {
    if (!window.currentPlan || window.currentPlan.length === 0) {
        showAlert('No data to export', 'warning');
        return;
    }

    try {
        // ✅ [แก้ไข]: คำนวณ Layer สำหรับสินค้าแต่ละชิ้นโดยใช้ Logic ที่ถูกต้อง (calculateExactLayer)
        const itemsWithLayers = window.currentPlan.map(item => {
            // ตรวจสอบว่าฟังก์ชัน calculateExactLayer มีอยู่จริง (อยู่ใน Global Scope)
            const layer = typeof calculateExactLayer === 'function' ? calculateExactLayer(item, window.currentPlan) : 1;
            return {
                ...item,
                layer: layer
            };
        });

        const planData = itemsWithLayers.map((item, index) => {
            // ✅ [แก้ไข]: ดึงขนาดที่จัดเรียงแล้ว (actualSize) หรือขนาดเดิม (length, width, height)
            const itemLength = (item.actualSize?.length || item.length) * 10; // cm to mm
            const itemWidth = (item.actualSize?.width || item.width) * 10; // cm to mm
            const itemHeight = (item.actualSize?.height || item.height) * 10; // cm to mm

            return {
                'No.': index + 1,
                'Item Name': item.name,
                'Position X (mm)': (item.position.x * 10).toFixed(1), // cm to mm
                'Position Y (mm)': (item.position.y * 10).toFixed(1), // cm to mm
                'Position Z (mm)': (item.position.z * 10).toFixed(1), // cm to mm
                'Length (mm)': itemLength.toFixed(1),
                'Width (mm)': itemWidth.toFixed(1),
                'Height (mm)': itemHeight.toFixed(1),
                'Weight (kg)': item.weight,
                'Layer': item.layer,
                'Rotation': item.rotationType === 'original' ? 'Normal' : 'Rotated',
                'Volume (m³)': ((itemLength * itemWidth * itemHeight) / 1000000000).toFixed(6)
            };
        });

        // ✅ สร้าง CSV header เป็นภาษาอังกฤษ
        let csv = 'No.,Item Name,Position X (mm),Position Y (mm),Position Z (mm),Length (mm),Width (mm),Height (mm),Weight (kg),Layer,Rotation,Volume\n';
        
        // ✅ เพิ่มข้อมูลแต่ละแถว
        planData.forEach(row => {
            // ใช้ toLocaleString('en-US') เพื่อให้ใช้จุดทศนิยมสำหรับตัวเลขทั้งหมด
            csv += `"${row['No.']}","${row['Item Name']}",${row['Position X (mm)'].replace(/,/g, '')},${row['Position Y (mm)'].replace(/,/g, '')},${row['Position Z (mm)'].replace(/,/g, '')},${row['Length (mm)'].replace(/,/g, '')},${row['Width (mm)'].replace(/,/g, '')},${row['Height (mm)'].replace(/,/g, '')},${row['Weight (kg)']},${row['Layer']},"${row['Rotation']}",${row['Volume (m³)']}\n`;
        });

        // ✅ สร้าง Blob และดาวน์โหลด
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        link.setAttribute('href', url);
        link.setAttribute('download', `container_loading_plan_${timestamp}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showAlert('Export completed successfully', 'success');
        console.log('✅ Export completed:', planData.length, 'items');
        
    } catch (error) {
        console.error('❌ Export error:', error);
        showAlert('Export failed: ' + error.message, 'danger');
    }
}

// เพิ่มตัวเลือกการจัดเรียงใหม่
function getLoadingConditions() {
    // ✅ [แก้ไข] ใช้ Optional Chaining และ Default Value เพื่อป้องกัน Cannot read properties of null (reading 'checked')
    const allowRotation = document.getElementById('allowRotation')?.checked || false;
    const stackingLimit = document.getElementById('stackingLimit')?.checked || false;
    
    return {
        sortingOrder: document.getElementById('sortingOrder')?.value || 'area',
        stackingLimit: stackingLimit,
        weightDistribution: document.getElementById('weightDistribution')?.checked || false,
        fragileTop: document.getElementById('fragileTop')?.checked || false,
        allowRotation: allowRotation,
        fillCorners: document.getElementById('fillCorners')?.checked || false,
        itemSpacing: parseInt(document.getElementById('itemSpacing')?.value) || 0,
        groupingMethod: document.getElementById('groupingMethod')?.value || 'none',
        noRotationItems: [],
        // ✅ ใช้ stackingLimit ที่ถูกตรวจสอบแล้ว
        useBaseAreaOnly: stackingLimit
    };
}

// ฟังก์ชันจัดเรียงสินค้าตามเงื่อนไข - แนวนอนเท่านั้น
function sortItemsByConditions(items, conditions) {
    const sortedItems = [...items];
    
    switch (conditions.sortingOrder) {
        case 'area':
            // ✅ เรียงตามพื้นที่ฐาน (ยาว x กว้าง)
            sortedItems.sort((a, b) => (b.length * b.width) - (a.length * a.width));
            break;
        case 'weight':
            sortedItems.sort((a, b) => b.weight - a.weight);
            break;
        case 'length':
            // ✅ เรียงตามความยาว
            sortedItems.sort((a, b) => b.length - a.length);
            break;
        case 'width':
            // ✅ เรียงตามความกว้าง
            sortedItems.sort((a, b) => b.width - a.width);
            break;
        case 'random':
            // สุ่มลำดับสินค้า
            for (let i = sortedItems.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [sortedItems[i], sortedItems[j]] = [sortedItems[j], sortedItems[i]];
            }
            break;
        default:
            // ✅ ค่า default: เรียงตามพื้นที่ฐาน
            sortedItems.sort((a, b) => (b.length * b.width) - (a.length * a.width));
    }
    
    return sortedItems;
}

// ฟังก์ชันดึงข้อมูลสินค้า
function getItemsData() {
    return items.map(item => ({
        ...item,
        volume: item.length * item.width * item.height
    }));
}

// เรียกใช้ก่อนคำนวณ
async function calculateLoading() {
    if (items.length === 0) {
        showAlert('กรุณาเพิ่มสินค้าอย่างน้อย 1 ชิ้น', 'warning');
        return;
    }

    // ปิดปุ่มคำนวณชั่วคราวเพื่อป้องกันการคลิกซ้ำ
    const calculateBtn = document.getElementById('calculateBtn');
    calculateBtn.disabled = true;
    calculateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>กำลังคำนวณ...';

    try {
  // รีเซ็ตการ mapping สีเมื่อเริ่มคำนวณใหม่
        resetItemTypeColors();

        // กำหนดสีให้สินค้าทั้งหมดใหม่ตามชนิด
        items.forEach(item => {
            item.color = getColorByItemType(item);
        });

        // รับค่าตู้คอนเทนเนอร์และเงื่อนไข
        const containerData = getContainerData();
        const conditions = getLoadingConditions();
        let itemsData = getItemsData();

        // ✅ นำเงื่อนไขไปใช้กับสินค้า
        itemsData = applyLoadingConditions(itemsData, conditions);
        
        console.log('📦 ข้อมูลสินค้าที่จะคำนวณ:', itemsData.length, 'ชิ้น');

        // ตรวจสอบข้อมูลก่อนคำนวณ
        const validationErrors = validateDataBeforeCalculation(itemsData, containerData);
        
        if (validationErrors.length > 0) {
            showAlert('พบข้อผิดพลาดในข้อมูล: ' + validationErrors.join(', '), 'danger');
            return;
        }

        // ✅ แสดง loading
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        const loadingText = document.getElementById('loadingText');
        
        loadingText.textContent = 'กำลังเรียงสินค้าทีละตัวตามขนาดฐาน...';
        loadingModal.show();

        // ใช้ setTimeout เพื่อให้ UI อัพเดทก่อนเริ่มคำนวณ
        await new Promise(resolve => setTimeout(resolve, 1000));

        loadingText.textContent = 'กำลังคำนวณการจัดเรียง...';

        // ✅ ใช้ algorithm จัดเรียงทีละตัวตามฐาน
        let results;

        if (conditions.useBaseAreaOnly) {
            results = sameBaseGroupLoadingAlgorithm(itemsData, containerData, conditions);
        } else {
            // โหมดซ้อนได้ - เรียกใช้ Advanced
            results = advancedLoadingAlgorithm(itemsData, containerData, conditions);
        }

        // ✅ ปิด loading modal
        closeLoadingModal();
        
        // แสดงผลลัพธ์
        displayResults(results, containerData);
        
    } catch (error) {
        console.error('❌ เกิดข้อผิดพลาดในการคำนวณ:', error);
        closeLoadingModal();
        showAlert('เกิดข้อผิดพลาดในการคำนวณ: ' + error.message, 'danger');
    } finally {
        // ✅ เปิดปุ่มคำนวณใหม่
        calculateBtn.disabled = false;
        calculateBtn.innerHTML = `<i class="fas fa-calculator me-1"></i>คำนวณการจัดเรียง (${items.length} ชิ้น)`;
    }
}

// ✅ ฟังก์ชันสำหรับปิด loading modal แบบแน่นอน (Force Close)
function closeLoadingModal() {
    console.log('🔄 กำลังปิด loading modal...');
    
    // 1. ซ่อน Modal element โดยตรง
    const loadingModalElement = document.getElementById('loadingModal');
    if (loadingModalElement) {
        loadingModalElement.style.display = 'none';
        loadingModalElement.classList.remove('show');
        loadingModalElement.setAttribute('aria-hidden', 'true');
        loadingModalElement.setAttribute('aria-modal', 'false');
    }
    
    // 2. พยายามเรียกใช้ Bootstrap Instance (วิธีที่ถูกต้อง)
    try {
        if (loadingModalElement) {
            const modalInstance = bootstrap.Modal.getInstance(loadingModalElement);
            if (modalInstance) {
                modalInstance.hide();
                console.log('✅ ปิดด้วย Bootstrap Instance');
            }
        }
    } catch (e) {
        console.warn('⚠️ ไม่สามารถหา Bootstrap Instance ได้:', e);
    }
    
    // 3. บังคับลบองค์ประกอบ Backdrop และคลาส body (วิธีที่เชื่อถือได้ที่สุด)
    forceRemoveModal();
    
    console.log('✅ ปิด loading modal เสร็จสมบูรณ์');
}

// ✅ ฟังก์ชันสำรองสำหรับลบ modal และ backdrop แบบบังคับ
function forceRemoveModal() {
    // A. ลบ backdrop ทั้งหมด
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        if (backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }
    });
    
    // B. ลบคลาส modal-open และ padding ที่เหลือจาก body
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // C. ตรวจสอบและซ่อน modal element อีกครั้ง
    const loadingModalElement = document.getElementById('loadingModal');
    if (loadingModalElement) {
        loadingModalElement.style.display = 'none';
        loadingModalElement.classList.remove('show');
    }
    
    console.log('✅ Force Remove Modal/Backdrop สำเร็จ');
}

// app.js - เพิ่มฟังก์ชันตรวจสอบข้อมูลก่อนคำนวณ
function validateDataBeforeCalculation(itemsToValidate, containerData) {
    const errors = [];
    
    // ตรวจสอบขนาดตู้
    if (containerData.length <= 0 || containerData.width <= 0 || containerData.height <= 0) {
        errors.push('ขนาดตู้คอนเทนเนอร์ต้องมากกว่า 0');
    }
    
    // ตรวจสอบน้ำหนักตู้
    if (containerData.maxWeight <= 0) {
        errors.push('น้ำหนักสูงสุดของตู้ต้องมากกว่า 0');
    }
    
    // ตรวจสอบสินค้า
    itemsToValidate.forEach((item, index) => {
        if (item.length <= 0 || item.width <= 0 || item.height <= 0) {
            errors.push(`สินค้า "${item.name}" มีขนาดไม่ถูกต้อง`);
        }
        
        if (item.weight <= 0) {
            errors.push(`สินค้า "${item.name}" มีน้ำหนักไม่ถูกต้อง`);
        }
        
        // ตรวจสอบว่าสินค้าใหญ่เกินตู้
        if (item.length > containerData.length || 
            item.width > containerData.width || 
            item.height > containerData.height) {
            errors.push(`สินค้า "${item.name}" ใหญ่เกินตู้คอนเทนเนอร์`);
        }
    });
    
    return errors;
}

// ✅ แก้ไขฟังก์ชัน showBaseConditionsSummary
function showBaseConditionsSummary(conditions) {
    if (!conditions) return;
    
    const conditionsHTML = `
        <div class="alert alert-info mt-3 conditions-summary">
            <h6><i class="fas fa-info-circle me-2"></i>เงื่อนไขการจัดเรียง</h6>
            <div class="row mt-2 small">
                <div class="col-md-6">
                    <strong>กลยุทธ์:</strong> จัดเรียงแบบติดผนังซ้าย-ขวา<br>
                    <strong>โหมด:</strong> ${conditions.useBaseAreaOnly ? 'จัดเรียงแนวนอน' : 'จัดเรียงแบบซ้อนได้'}<br>
                    <strong>จำนวนชั้นสูงสุด:</strong> ${conditions.useBaseAreaOnly ? '1 ชั้น' : 'ไม่จำกัด'}<br>
                    <strong>การหมุนสินค้า:</strong> ${conditions.allowRotation ? 'อนุญาต' : 'ไม่อนุญาต'}
                </div>
                <div class="col-md-6">
                    <strong>ระยะห่างระหว่างสินค้า:</strong> ${conditions.itemSpacing || 0} cm<br>
                    <strong>การซ้อนกัน:</strong> ${conditions.useBaseAreaOnly ? 'ปิดใช้งาน' : 'เปิดใช้งาน'}<br>
                    <strong>จัดกลุ่ม:</strong> ${getGroupingMethodText(conditions.groupingMethod)}<br>
                    <strong>การเรียงลำดับ:</strong> ${getSortingOrderText(conditions.sortingOrder)}
                </div>
            </div>
            <div class="mt-2 p-2 bg-light border rounded">
                <small>
                    <i class="fas fa-lightbulb me-1 text-warning"></i>
                    <strong>กลยุทธ์ติดผนัง:</strong> จัดเรียงสินค้าติดผนังซ้ายและขวาก่อน เพื่อสร้างความมั่นคงและป้องกันการล้ม
                </small>
            </div>
        </div>
    `;
    
    const loadingPlanCard = document.querySelector('.card:has(#loadingPlan)');
    loadingPlanCard.querySelector('.card-body').insertAdjacentHTML('afterbegin', conditionsHTML);
}

// ✅ แก้ไขฟังก์ชัน showUnpackedItems ให้แสดงต่อจากตาราง
function showUnpackedItems(packedItems, totalItemCount) {
    const unpackedCount = totalItemCount - packedItems.length;
    
    if (unpackedCount <= 0) {
        return; // ไม่แสดงอะไรถ้าจัดได้ทั้งหมด
    }
    
    // หาสินค้าที่จัดไม่ได้
    const packedItemIds = new Set(packedItems.map(item => item.id));
    const unpackedItems = items.filter(item => !packedItemIds.has(item.id));
    
    const unpackedHTML = `
        <div class="mt-4 unpacked-items-alert">
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>สินค้าที่จัดเรียงไม่ได้ (${unpackedCount} ชิ้น)</h6>
                <div class="mt-2">
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อสินค้า</th>
                                <th>ขนาด (mm)</th>
                                <th>น้ำหนัก (kg)</th>
                                <th>ปริมาตร (m³)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${unpackedItems.map(item => {
                                const volumeM3 = (item.originalLength * item.originalWidth * item.originalHeight) / 1000000000;
                                return `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.originalLength}×${item.originalWidth}×${item.originalHeight}</td>
                                        <td>${item.weight}</td>
                                        <td>${volumeM3.toFixed(4)}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>คำแนะนำ:</strong> ลองปรับเปลี่ยนเงื่อนไขการจัดเรียง อนุญาตการหมุนสินค้า หรือลดขนาดสินค้า
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // แทรกหลังจากตารางแผนการจัดเรียง
    const loadingPlanTable = document.getElementById('loadingPlan').closest('.table-responsive');
    loadingPlanTable.insertAdjacentHTML('afterend', unpackedHTML);
}

function getContainerData() {
    const containerType = document.getElementById('containerType').value;
    
    if (containerType === 'custom') {
        const length = parseInt(document.getElementById('customLength').value) || 589;
        const width = parseInt(document.getElementById('customWidth').value) || 235;
        const height = parseInt(document.getElementById('customHeight').value) || 239;
        const maxWeight = parseInt(document.getElementById('maxWeight').value) || 28000;
        
        return {
            length: length,
            width: width, 
            height: height,
            maxWeight: maxWeight,
            volume: function() {
                return this.length * this.width * this.height;
            }
        };
    } else {
        const containerTypes = {
            '20ft': { length: 589, width: 235, height: 239, maxWeight: 28000 },
            '40ft': { length: 1203, width: 235, height: 239, maxWeight: 28000 },
            '40hq': { length: 1203, width: 235, height: 269, maxWeight: 28000 }
        };
        const container = containerTypes[containerType];
        return {
            ...container,
            volume: function() {
                return this.length * this.width * this.height;
            }
        };
    }
}

// =============================================
// ฟังก์ชันสำหรับโหลดสินค้าจากไฟล์ CSV (แบบง่าย)
// =============================================

// ตัวแปรเก็บข้อมูล CSV
let csvData = [];

// เพิ่ม event listener สำหรับการเลือกไฟล์ CSV
document.getElementById('csvFile').addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : '';
    document.getElementById('csvFileName').value = fileName;
});

// ตั้งค่า event listener สำหรับไฟล์ CSV
function initializeCSVEvents() {
    // ไม่ต้องทำอะไรที่นี่ จะไปตั้งค่าในฟังก์ชัน loadItemsFromCSV แทน
    console.log('📝 CSV events จะถูกตั้งค่าเมื่อมีการใช้งาน');
}


// ฟังก์ชันโหลดและประมวลผลไฟล์ CSV
function loadItemsFromCSV() {
    const fileInput = document.getElementById('csvFile');
    
    if (!fileInput.files.length) {
        showAlert('กรุณาเลือกไฟล์ CSV', 'warning');
        return;
    }

    const file = fileInput.files[0];
    
    // ซ่อนผลลัพธ์เก่าและแสดง loading
    document.getElementById('csvResults').classList.add('d-none');
    document.getElementById('csvError').classList.add('d-none');
    document.getElementById('csvLoading').classList.remove('d-none');

    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const csvText = e.target.result;
            console.log('📄 เนื้อหาไฟล์ CSV:', csvText.substring(0, 500));
            
            // ประมวลผล CSV
            const items = parseCSVData(csvText);
            csvData = items;
            
            // ซ่อน loading
            document.getElementById('csvLoading').classList.add('d-none');
            
            if (items.length > 0) {
                displayCSVItems(items);
                showAlert(`พบสินค้า ${items.length} รายการในไฟล์ CSV`, 'success');
            } else {
                showCSVError('ไม่พบข้อมูลสินค้าในไฟล์ CSV หรือรูปแบบไฟล์ไม่ถูกต้อง');
            }
            
        } catch (error) {
            console.error('❌ Error processing CSV:', error);
            document.getElementById('csvLoading').classList.add('d-none');
            showCSVError('เกิดข้อผิดพลาดในการประมวลผลไฟล์ CSV: ' + error.message);
        }
    };
    
    reader.onerror = function() {
        document.getElementById('csvLoading').classList.add('d-none');
        showCSVError('ไม่สามารถอ่านไฟล์ได้');
    };
    
    reader.readAsText(file, 'UTF-8');
}

// ฟังก์ชันแปลงข้อมูล CSV เป็น array ของสินค้า
function parseCSVData(csvText) {
    const items = [];
    const lines = csvText.split('\n').filter(line => line.trim() !== '');
    
    console.log('📊 จำนวนบรรทัดในไฟล์:', lines.length);
    
    if (lines.length < 2) {
        throw new Error('ไฟล์ CSV มีข้อมูลไม่เพียงพอ');
    }
    
    // อ่าน header
    const headers = parseCSVLine(lines[0]);
    console.log('📋 Headers:', headers);
    
    // ประมวลผลข้อมูล
    for (let i = 1; i < lines.length; i++) {
        try {
            const row = parseCSVLine(lines[i]);
            if (row.length >= 7) {
                // ดึงข้อมูลจากคอลัมน์ต่างๆ
                const itemNo = row[0]?.trim() || `${i}`;
                const plateNo = row[2]?.trim() || `ITEM-${itemNo}`;
                
                // แปลงขนาดและน้ำหนัก (ลบ comma และแปลงเป็น number)
                const length = parseFloat(String(row[3]).replace(/,/g, '')) || 0;
                const width = parseFloat(String(row[4]).replace(/,/g, '')) || 0;
                const height = parseFloat(String(row[5]).replace(/,/g, '')) || 0;
                const weight = parseFloat(String(row[6]).replace(/,/g, '')) || 0;
                
                // ตรวจสอบว่าข้อมูลถูกต้อง
                if (length > 0 && width > 0 && height > 0 && weight > 0) {
                    items.push({
                        itemNo: itemNo,
                        plateNo: plateNo,
                        length: length,
                        width: width,
                        height: height,
                        weight: weight
                    });
                    
                    console.log(`✅ สินค้า ${itemNo}: ${plateNo} - ${length}x${width}x${height}mm - ${weight}kg`);
                }
            }
        } catch (error) {
            console.warn(`⚠️ ข้ามบรรทัดที่ ${i + 1}:`, error.message);
        }
    }
    
    console.log('✅ ประมวลผล CSV สำเร็จ:', items.length, 'รายการ');
    return items;
}

// ฟังก์ชันแยกข้อมูลจากบรรทัด CSV (รองรับ quoted values)
function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    
    // เพิ่มค่าสุดท้าย
    result.push(current.trim());
    
    return result;
}

// แสดงรายการสินค้าจาก CSV
function displayCSVItems(items) {
    const container = document.getElementById('csvItemsList');
    const resultsDiv = document.getElementById('csvResults');
    
    console.log('📊 แสดงรายการสินค้าจาก CSV:', items.length, 'รายการ');
    
    container.innerHTML = '';
    
    items.forEach((item, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="checkbox" class="csv-item-checkbox" data-index="${index}" checked>
            </td>
            <td>${item.itemNo}</td>
            <td>${item.plateNo}</td>
            <td>${item.length.toLocaleString()} × ${item.width.toLocaleString()} × ${item.height.toLocaleString()} mm</td>
            <td>${item.weight.toLocaleString()} kg</td>
        `;
        container.appendChild(row);
    });

    // เพิ่ม event listener สำหรับเลือกทั้งหมด
    const selectAllCheckbox = document.getElementById('selectAllCSVItems');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.csv-item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    resultsDiv.classList.remove('d-none');
}

// แสดงข้อผิดพลาด CSV
function showCSVError(message) {
    const errorDiv = document.getElementById('csvError');
    errorDiv.textContent = message;
    errorDiv.classList.remove('d-none');
    console.error('❌ ข้อผิดพลาด CSV:', message);
}

// เพิ่มสินค้าที่เลือกจาก CSV
function addSelectedCSVItems() {
    const checkboxes = document.querySelectorAll('.csv-item-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showAlert('กรุณาเลือกสินค้าอย่างน้อย 1 รายการ', 'warning');
        return;
    }

    let addedCount = 0;

    checkboxes.forEach(checkbox => {
        const index = parseInt(checkbox.dataset.index);
        const item = csvData[index];
        
        if (item) {
            // สร้างสินค้าใหม่จากข้อมูล CSV
            const itemId = Date.now() + index;
            const name = `${item.plateNo}`;
            const itemColor = getColorByItemType(name);
            
            // แปลงหน่วยจาก mm เป็น cm สำหรับการคำนวณ
            const lengthCm = item.length / 10;
            const widthCm = item.width / 10;
            const heightCm = item.height / 10;
            
            items.push({
                id: itemId,
                name: name,
                originalName: name,
                length: lengthCm,
                width: widthCm,
                height: heightCm,
                weight: item.weight,
                volume: lengthCm * widthCm * heightCm,
                color: itemColor,
                originalLength: item.length,
                originalWidth: item.width,
                originalHeight: item.height,
                source: 'csv',
                plateNo: item.plateNo
            });
            
            addedCount++;
        }
    });

    // อัพเดทรายการสินค้าและซ่อนผลลัพธ์
    updateItemsList();
    document.getElementById('csvResults').classList.add('d-none');
    document.getElementById('csvFile').value = '';
    document.getElementById('csvFileName').value = '';
    
    showAlert(`เพิ่มสินค้าจากไฟล์ CSV ${addedCount} รายการเรียบร้อยแล้ว`, 'success');
    console.log(`✅ เพิ่มสินค้า ${addedCount} รายการจาก CSV`);
}

// =============================================
// ฟังก์ชันสำหรับสลับมุมมอง 2D/3D
// =============================================

// แก้ไขฟังก์ชัน toggleViewMode
function toggleViewMode() {
    console.log('🔄 สลับมุมมอง 2D/3D');
    
    // ตรวจสอบว่ามีข้อมูลการจัดเรียงหรือไม่
    if (!window.currentPlan || window.currentPlan.length === 0) {
        showAlert('❌ ยังไม่มีข้อมูลการจัดเรียง กรุณาคำนวณการจัดเรียงก่อน', 'warning');
        return;
    }

    const containerElem = document.getElementById('visualization');
    const view2DContainer = document.getElementById('view2DContainer');
    
    // ✅ ตรวจสอบว่ากำลังแสดงมุมมองอะไรอยู่
    const isShowing2D = view2DContainer && view2DContainer.style.display !== 'none';
    const isShowing3D = containerElem.querySelector('canvas') !== null;
    
    console.log('🔍 สถานะมุมมองปัจจุบัน:', {
        isShowing2D: isShowing2D,
        isShowing3D: isShowing3D,
        hasCurrentPlan: !!window.currentPlan,
        planLength: window.currentPlan ? window.currentPlan.length : 0
    });

    try {
        // ✅ ล้าง container ให้สะอาดทุกครั้งก่อนสร้างใหม่
        containerElem.innerHTML = '';

        if (isShowing2D || !isShowing3D) {
            // ✅ เปลี่ยนจาก 2D เป็น 3D
            console.log('🔄 เปลี่ยนจาก 2D เป็น 3D');
            
            // สร้าง 3D visualization
            const visualizationResult = create3DVisualization(
                window.currentPlan, 
                window.currentContainer, 
                window.currentConditions || {}
            );
            
            if (visualizationResult) {
                showAlert('✅ เปลี่ยนเป็นมุมมอง 3D', 'success');
                updateToggleViewButton(true);
            } else {
                throw new Error('สร้าง 3D Visualization ไม่สำเร็จ');
            }
        } else {
            // ✅ เปลี่ยนจาก 3D เป็น 2D
            console.log('🔄 เปลี่ยนจาก 3D เป็น 2D');
            
            // สร้าง 2D view
            createSimple2DView(window.currentPlan, window.currentContainer);
            showAlert('✅ เปลี่ยนเป็นมุมมอง 2D', 'success');
            updateToggleViewButton(false);
        }
        
    } catch (error) {
        console.error('❌ เกิดข้อผิดพลาดในการสลับมุมมอง:', error);
        showAlert('❌ ไม่สามารถสลับมุมมองได้: ' + error.message, 'danger');
        
        // ✅ Fallback: พยายามสร้าง 2D view
        try {
            containerElem.innerHTML = '';
            createSimple2DView(window.currentPlan, window.currentContainer);
            updateToggleViewButton(false);
            showAlert('✅ เปลี่ยนเป็นมุมมอง 2D (โหมดสำรอง)', 'info');
        } catch (fallbackError) {
            console.error('❌ Fallback ก็ล้มเหลว:', fallbackError);
            containerElem.innerHTML = `
                <div class="text-center p-5 text-muted">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h5>ไม่สามารถโหลด visualization ได้</h5>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-redo me-1"></i>รีเฟรชหน้า
                    </button>
                </div>
            `;
        }
    }
}

// ✅ เพิ่มฟังก์ชันอัพเดทข้อความปุ่มสลับมุมมอง
function updateToggleViewButton(is3DView) {
    const toggleBtn = document.getElementById('toggleViewBtn');
    if (toggleBtn) {
        if (is3DView) {
            toggleBtn.innerHTML = '<i class="fas fa-map me-1"></i> 2D';
            toggleBtn.title = 'สลับเป็นมุมมอง 2D';
            toggleBtn.classList.remove('btn-outline-success');
            toggleBtn.classList.add('btn-outline-primary');
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-cube me-1"></i> 3D';
            toggleBtn.title = 'สลับเป็นมุมมอง 3D';
            toggleBtn.classList.remove('btn-outline-primary');
            toggleBtn.classList.add('btn-outline-success');
        }
    }
}

// ฟังก์ชันสร้าง 2D view แบบง่าย (สำรอง)
function createSimple2DView(itemsToVisualize, containerData) {
    const containerElem = document.getElementById('visualization');
    
    // ล้าง container ให้สะอาด
    containerElem.innerHTML = '';
    
    const view2DContainer = document.createElement('div');
    view2DContainer.id = 'view2DContainer';
    view2DContainer.style.cssText = `
        width: 100%;
        height: 500px;
        background: white;
        position: relative;
        border: 2px solid #ccc;
        border-radius: 5px;
        overflow: hidden;
    `;
    containerElem.appendChild(view2DContainer);
    
    // คำนวณ scale สำหรับการวาด
    const drawingWidth = view2DContainer.offsetWidth - 40;
    const drawingHeight = view2DContainer.offsetHeight - 40;
    const scaleX = drawingWidth / containerData.length;
    const scaleY = drawingHeight / containerData.width;
    const scale = Math.min(scaleX, scaleY) * 0.9;

    // ✅ สร้าง container สำหรับสินค้า
    const itemsContainer = document.createElement('div');
    itemsContainer.style.cssText = `
        position: absolute;
        top: 20px;
        left: 20px;
        width: ${containerData.length * scale}px;
        height: ${containerData.width * scale}px;
        background: #f8f9fa;
        border: 2px solid #2c3e50;
    `;
    view2DContainer.appendChild(itemsContainer);
    
    // ✅ เพิ่มข้อความขนาดตู้
    const containerLabel = document.createElement('div');
    containerLabel.style.cssText = `
        position: absolute;
        top: 5px;
        left: 25px;
        background: rgba(44, 62, 80, 0.9);
        color: white;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        z-index: 5;
    `;
    containerLabel.textContent = `Container: ${containerData.length} × ${containerData.width} cm`;
    view2DContainer.appendChild(containerLabel);
    
    // ✅ กลุ่มสินค้าตามตำแหน่งเพื่อจัดการการซ้อนกัน
    const positionGroups = new Map();
    
    if (itemsToVisualize && itemsToVisualize.length > 0) {
        itemsToVisualize.forEach((item, index) => {
            if (!item.position) return;
            
            const itemLength = (item.actualSize?.length || item.length);
            const itemWidth = (item.actualSize?.width || item.width);
            
            // คำนวณตำแหน่งและขนาด
            const x = item.position.x * scale;
            const y = item.position.y * scale;
            const widthPx = itemLength * scale;
            const heightPx = itemWidth * scale;
            
            // ✅ สร้าง key สำหรับกลุ่มตำแหน่ง (ใช้พิกัดที่ปัดเศษเพื่อรวมสินค้าที่ซ้อนกัน)
            const positionKey = `${Math.floor(x/5)}-${Math.floor(y/5)}`;
            
            if (!positionGroups.has(positionKey)) {
                positionGroups.set(positionKey, []);
            }
            positionGroups.get(positionKey).push({
                item: item,
                x: x,
                y: y,
                widthPx: widthPx,
                heightPx: heightPx,
                index: index,
                z: item.position.z || 0 // ✅ เก็บความสูงสำหรับเรียงลำดับ
            });
        });
    }
    
    // ✅ เรียงกลุ่มตามความสูง (สินค้าที่อยู่ต่ำกว่ามาก่อน)
    positionGroups.forEach(itemsInGroup => {
        itemsInGroup.sort((a, b) => a.z - b.z);
    });
    
    // ✅ วาดสินค้าแต่ละกลุ่มด้วยการจัดการการซ้อนกัน
    let placedItems = 0;
    
    positionGroups.forEach((itemsInGroup, positionKey) => {
        if (itemsInGroup.length === 1) {
            // ✅ ถ้ามีสินค้าเดียวในตำแหน่งนี้
            const { item, x, y, widthPx, heightPx } = itemsInGroup[0];
            createSingleItemElement(item, x, y, widthPx, heightPx, itemsContainer, 0);
            placedItems++;
        } else {
            // ✅ ถ้ามีหลายสินค้าในตำแหน่งใกล้กัน - สร้างกลุ่ม
            createStackedItemsGroup(itemsInGroup, itemsContainer);
            placedItems += itemsInGroup.length;
        }
    });
    
    // ✅ เพิ่มข้อมูลสรุป
    const summary = document.createElement('div');
    summary.style.cssText = `
        position: absolute;
        bottom: 10px;
        left: 20px;
        background: rgba(255, 255, 255, 0.95);
        padding: 6px 10px;
        border-radius: 5px;
        font-size: 11px;
        border: 1px solid #ddd;
        z-index: 100;
    `;
    summary.innerHTML = `
        <strong>Packed: ${placedItems}/${itemsToVisualize.length} items</strong>
        <div style="font-size: 9px; color: #666; margin-top: 2px;">
            • Click items for details
        </div>
    `;
    view2DContainer.appendChild(summary);
    
    console.log(`✅ Created 2D view with stacked items: ${placedItems} items in ${positionGroups.size} groups`);
}

// ✅ ฟังก์ชันสร้างกลุ่มสินค้าที่ซ้อนกัน
function createStackedItemsGroup(itemsInGroup, container) {
    const firstItem = itemsInGroup[0];
    const { x, y, widthPx, heightPx } = firstItem;
    
    // ✅ สร้าง container กลุ่ม
    const groupContainer = document.createElement('div');
    groupContainer.style.cssText = `
        position: absolute;
        left: ${x}px;
        top: ${y}px;
        width: ${widthPx}px;
        height: ${heightPx}px;
        z-index: ${10 + itemsInGroup.length}; // ✅ z-index ตามจำนวนสินค้าในกลุ่ม
    `;
    
    // ✅ สร้างกล่องกลุ่มที่มีชื่อสินค้าทั้งหมด
    const groupBox = document.createElement('div');
    groupBox.style.cssText = `
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: 2px solid #fff;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        font-weight: bold;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        padding: 4px;
        box-sizing: border-box;
        overflow: hidden;
    `;
    
    // ✅ แสดงชื่อสินค้าทั้งหมดในกลุ่ม (เรียงตามชั้น)
    const itemNames = itemsInGroup.map(itemData => {
        const item = itemData.item;
        let displayName = item.name;
        const maxNameLength = Math.floor(widthPx / 10);
        
        if (displayName.length > maxNameLength && maxNameLength > 3) {
            displayName = displayName.substring(0, maxNameLength - 1) + '…';
        }
        
        // ✅ เพิ่มเลขชั้นถ้ามีการซ้อนกัน
        const layer = Math.floor(itemData.z / (item.actualSize?.height || item.height)) + 1;
        return `${displayName} (L${layer})`;
    }).join(', ');
    
    groupBox.innerHTML = `
        <div style="font-size: ${Math.max(8, Math.min(10, Math.min(widthPx, heightPx) * 0.12))}px; line-height: 1.2;">
            ${itemNames}
        </div>
        <div style="font-size: ${Math.max(6, Math.min(8, Math.min(widthPx, heightPx) * 0.1))}px; margin-top: 2px; opacity: 0.9;">
            ${itemsInGroup.length} items
        </div>
    `;
    
    groupBox.onmouseenter = function() {
        this.style.transform = 'scale(1.05)';
        this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
    };
    
    groupBox.onmouseleave = function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
    };
    
    // ✅ คลิกเพื่อแสดงรายการสินค้าในกลุ่ม
    groupBox.onclick = function(e) {
        e.stopPropagation();
        showItemsGroupModal(itemsInGroup);
    };
    
    groupContainer.appendChild(groupBox);
    container.appendChild(groupContainer);
}

// ✅ สร้าง element สินค้าเดียว - แสดงเฉพาะชื่อ
function createSingleItemElement(item, x, y, widthPx, heightPx, container, zIndex = 0) {
    const itemDiv = document.createElement('div');
    itemDiv.style.cssText = `
        position: absolute;
        left: ${x}px;
        top: ${y}px;
        width: ${widthPx}px;
        height: ${heightPx}px;
        background: ${item.color || '#3498db'};
        border: 1px solid #000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: ${Math.max(8, Math.min(12, Math.min(widthPx, heightPx) * 0.15))}px;
        font-weight: bold;
        color: #000;
        text-align: center;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.2s;
        z-index: ${10 + zIndex};
        padding: 2px;
        box-sizing: border-box;
    `;
    
    // ✅ แสดงชื่อสินค้าเท่านั้น (ปรับตามขนาด)
    let displayName = item.name;
    const fontSize = Math.max(8, Math.min(12, Math.min(widthPx, heightPx) * 0.15));
    const maxChars = Math.floor(widthPx / (fontSize * 0.6));
    
    // ✅ ตัดชื่อให้พอดีกับกล่อง
    if (displayName.length > maxChars && maxChars > 3) {
        displayName = displayName.substring(0, maxChars - 1) + '…';
    }
    
    itemDiv.innerHTML = `
        <div style="transform: scale(${Math.min(1, heightPx/40)});">
            ${displayName}
        </div>
    `;
    
    // ✅ Tooltip แสดงข้อมูลเต็ม
    const itemLength = (item.actualSize?.length || item.length);
    const itemWidth = (item.actualSize?.width || item.width);
    const layer = Math.floor((item.position?.z || 0) / (item.actualSize?.height || item.height)) + 1;
    
    itemDiv.title = `${item.name}\nSize: ${itemLength} × ${itemWidth} cm\nPosition: (${item.position?.x || 0}, ${item.position?.y || 0}) cm\nLayer: ${layer}\nWeight: ${item.weight} kg`;
    
    // ✅ Effect เมื่อ hover
    itemDiv.onmouseenter = function() {
        this.style.zIndex = '1000';
        this.style.boxShadow = '0 0 8px rgba(0,0,0,0.5)';
        this.style.border = '2px solid #e74c3c';
        this.style.transform = 'scale(1.02)';
    };
    
    itemDiv.onmouseleave = function() {
        this.style.zIndex = `${10 + zIndex}`;
        this.style.boxShadow = 'none';
        this.style.border = '1px solid #000';
        this.style.transform = 'scale(1)';
    };
    
    // ✅ คลิกเพื่อแสดง modal รายละเอียด
    itemDiv.onclick = function() {
        showItemDetailModal(item);
    };
    
    container.appendChild(itemDiv);
    return itemDiv;
}

// ✅ แสดง Modal รายละเอียดสินค้าเดียว
function showItemDetailModal(item) {
    // สร้าง modal HTML
    const modalHTML = `
        <div class="modal fade" id="itemDetailModal" tabindex="-1" aria-labelledby="itemDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="itemDetailModalLabel">📦 รายละเอียดสินค้า</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>ชื่อ:</strong> ${item.name}
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <strong>ขนาด:</strong><br>
                                ${(item.actualSize?.length || item.length)} × ${(item.actualSize?.width || item.width)} cm
                            </div>
                            <div class="col-6">
                                <strong>น้ำหนัก:</strong><br>
                                ${item.weight} kg
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>ตำแหน่ง:</strong><br>
                            X: ${item.position.x} cm, Y: ${item.position.y} cm
                        </div>
                        <div class="mt-2">
                            <div style="width: 100%; height: 20px; background: ${item.color || '#3498db'}; border-radius: 3px;"></div>
                            <small class="text-muted">สีแสดงในแผนที่</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // ลบ modal เก่าถ้ามี
    const existingModal = document.getElementById('itemDetailModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // เพิ่ม modal ใหม่
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // แสดง modal
    const modal = new bootstrap.Modal(document.getElementById('itemDetailModal'));
    modal.show();
    
    // ✅ ลบ modal ออกเมื่อปิด
    document.getElementById('itemDetailModal').addEventListener('hidden.bs.modal', function () {
        setTimeout(() => {
            if (this.parentNode) {
                this.parentNode.removeChild(this);
            }
        }, 300);
    });
}

// ✅ แสดง Modal รายการสินค้าในกลุ่ม
function showItemsGroupModal(itemsInGroup) {
    const itemsListHTML = itemsInGroup.map(itemData => {
        const item = itemData.item;
        const itemLength = (item.actualSize?.length || item.length);
        const itemWidth = (item.actualSize?.width || item.width);
        
        return `
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex align-items-center mb-1">
                    <div style="width: 12px; height: 12px; background: ${item.color || '#3498db'}; margin-right: 8px; border-radius: 2px;"></div>
                    <strong>${item.name}</strong>
                </div>
                <div class="small text-muted">
                    ขนาด: ${itemLength} × ${itemWidth} cm | น้ำหนัก: ${item.weight} kg<br>
                    ตำแหน่ง: (${item.position.x}, ${item.position.y}) cm
                </div>
            </div>
        `;
    }).join('');
    
    const modalHTML = `
        <div class="modal fade" id="itemsGroupModal" tabindex="-1" aria-labelledby="itemsGroupModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="itemsGroupModalLabel">📁 กลุ่มสินค้า (${itemsInGroup.length} ชิ้น)</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                    </div>
                    <div class="modal-body">
                        <div style="max-height: 300px; overflow-y: auto;">
                            ${itemsListHTML}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                        <small class="text-muted ms-2">สินค้าเหล่านี้อยู่ในตำแหน่งใกล้เคียงกัน</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // ลบ modal เก่าถ้ามี
    const existingModal = document.getElementById('itemsGroupModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // เพิ่ม modal ใหม่
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // แสดง modal
    const modal = new bootstrap.Modal(document.getElementById('itemsGroupModal'));
    modal.show();
    
    // ✅ ลบ modal ออกเมื่อปิด
    document.getElementById('itemsGroupModal').addEventListener('hidden.bs.modal', function () {
        setTimeout(() => {
            if (this.parentNode) {
                this.parentNode.removeChild(this);
            }
        }, 300);
    });
}

// ✅ เพิ่มฟังก์ชันอัพเดทข้อความปุ่มสลับมุมมอง
function updateToggleViewButton(is3DView) {
    const toggleBtn = document.getElementById('toggleViewBtn');
    if (toggleBtn) {
        if (is3DView) {
            toggleBtn.innerHTML = '<i class="fas fa-map me-1"></i> 2D';
            toggleBtn.title = 'สลับเป็นมุมมอง 2D';
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-cube me-1"></i> 3D';
            toggleBtn.title = 'สลับเป็นมุมมอง 3D';
        }
    }
}

function 💾 Save2DImage() {
    const view2DContainer = document.getElementById('view2DContainer');
    
    if (!view2DContainer) {
        showAlert('ไม่พบมุมมอง 2D ที่จะบันทึก', 'warning');
        return;
    }

    // ✅ สร้าง loading แยกต่างหาก ไม่ใส่ใน container
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'savingLoading';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        z-index: 10000;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังสร้างภาพ...';
    document.body.appendChild(loadingDiv);

    // ✅ ใช้ timeout เพื่อให้แสดง loading ก่อน
    setTimeout(() => {
        // ใช้ html2canvas เพื่อบันทึกภาพ
        html2canvas(view2DContainer, {
            backgroundColor: '#ffffff',
            scale: 2, // ความละเอียดสูง
            useCORS: true,
            allowTaint: false,
            logging: false,
            removeContainer: true // ✅ ป้องกันการแสดง loading ในภาพ
        }).then(canvas => {
            // ✅ ลบ loading
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            
            // ✅ สร้างลิงก์ดาวน์โหลด
            const link = document.createElement('a');
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            link.download = `container_2d_view_${timestamp}.png`;
            link.href = canvas.toDataURL('image/png');
            
            // ✅ ใช้คลิกแบบปลอดภัย
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showAlert('บันทึกภาพมุมมอง 2D เรียบร้อยแล้ว', 'success');
        }).catch(error => {
            console.error('❌ เกิดข้อผิดพลาดในการบันทึกภาพ 2D:', error);
            // ✅ ลบ loading ถ้ามีข้อผิดพลาด
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            showAlert('เกิดข้อผิดพลาดในการบันทึกภาพ: ' + error.message, 'danger');
        });
    }, 100);
}

// ✅ เพิ่มฟังก์ชันบันทึกภาพมุมมอง 3D (ถ้ายังไม่มี)
function 💾 Save3DImage() {
    if (!window.currentRenderer || !window.currentScene || !window.currentCamera) {
        showAlert('ไม่พบมุมมอง 3D ที่จะบันทึก', 'warning');
        return;
    }

    console.log('📸 เริ่มบันทึกภาพ 3D...');

    // ✅ แสดง loading
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'savingLoading3D';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        z-index: 10000;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังสร้างภาพ 3D...';
    document.body.appendChild(loadingDiv);

    // ✅ สร้าง renderer ชั่วคราวสำหรับการบันทึกภาพ
    setTimeout(() => {
        try {
            const originalCanvas = window.currentRenderer.domElement;
            
            // ✅ สร้าง renderer ชั่วคราว
            const tempRenderer = new THREE.WebGLRenderer({ 
                preserveDrawingBuffer: true,
                antialias: true 
            });
            tempRenderer.setSize(originalCanvas.width, originalCanvas.height);
            tempRenderer.setClearColor(0xf0f8ff, 1);
            
            // ✅ คัดลอก camera settings
            const tempCamera = window.currentCamera.clone();
            
            // ✅ เรนเดอร์ด้วย renderer ชั่วคราว
            tempRenderer.render(window.currentScene, tempCamera);
            
            // ✅ ได้ canvas จาก renderer ชั่วคราว
            const tempCanvas = tempRenderer.domElement;
            
            // ✅ แปลงเป็น data URL
            const dataURL = tempCanvas.toDataURL('image/png');
            
            if (!dataURL || dataURL === 'data:,') {
                throw new Error('Failed to generate image data from temporary renderer');
            }

            // ✅ ลบ loading
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }

            // ✅ ทำความสะอาด
            tempRenderer.dispose();
            
            // ✅ สร้างลิงก์ดาวน์โหลด
            const link = document.createElement('a');
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            link.download = `container_3d_view_${timestamp}.png`;
            link.href = dataURL;
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            console.log('✅ บันทึกภาพ 3D สำเร็จ (ใช้ temporary renderer)');
            showAlert('บันทึกภาพมุมมอง 3D เรียบร้อยแล้ว', 'success');

        } catch (error) {
            console.error('❌ เกิดข้อผิดพลาดในการบันทึกภาพ 3D:', error);
            // ✅ ลบ loading ถ้ามีข้อผิดพลาด
            if (loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
            showAlert('เกิดข้อผิดพลาดในการบันทึกภาพ 3D: ' + error.message, 'danger');
        }
    }, 100);
}

// ✅ เพิ่มฟังก์ชันจัดการคลิกเมาส์ขวา
function setupRightClick💾 Save() {
    const visualization = document.getElementById('visualization');
    
    visualization.addEventListener('contextmenu', function(e) {
        e.preventDefault(); // ป้องกันเมนูมาตรฐาน
        
        const view2DContainer = document.getElementById('view2DContainer');
        const threeDCanvas = visualization.querySelector('canvas');
        
        // ตรวจสอบว่ากำลังแสดงมุมมองอะไร
        if (view2DContainer && view2DContainer.style.display !== 'none') {
            // มุมมอง 2D
            💾 Save2DImage();
        } else if (threeDCanvas && threeDCanvas.style.display !== 'none') {
            // มุมมอง 3D
            💾 Save3DImage();
        } else {
            showAlert('ไม่พบมุมมองที่สามารถบันทึกได้', 'warning');
        }
    });
}

// ✅ เพิ่มฟังก์ชันสร้างเมนูคลิกขวา (แบบสวยงาม)
function createRightClickMenu(x, y, is2DView) {
    // ลบเมนูเก่าถ้ามี
    const existingMenu = document.getElementById('rightClickMenu');
    if (existingMenu) {
        existingMenu.remove();
    }
    
    const menu = document.createElement('div');
    menu.id = 'rightClickMenu';
    menu.style.cssText = `
        position: fixed;
        top: ${y}px;
        left: ${x}px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        min-width: 200px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        animation: menuFadeIn 0.15s ease-out;
    `;
    
    const menuItem = document.createElement('div');
    menuItem.style.cssText = `
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        transition: background-color 0.2s;
        font-size: 14px;
    `;
    menuItem.innerHTML = `
        <i class="fas fa-camera me-2" style="color: #3498db;"></i>
        บันทึกภาพมุมมอง ${is2DView ? '2D' : '3D'}
    `;
    
    menuItem.onmouseenter = () => menuItem.style.backgroundColor = '#f8f9fa';
    menuItem.onmouseleave = () => menuItem.style.backgroundColor = 'white';
    menuItem.onclick = () => {
        menu.remove();
        if (is2DView) {
            💾 Save2DImage();
        } else {
            💾 Save3DImage();
        }
    };
    
    menu.appendChild(menuItem);
    document.body.appendChild(menu);
    
    // ✅ ปิดเมนูเมื่อคลิก其他地方
    const closeMenu = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeMenu);
        }
    };
    
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 100);
}

// ✅ อัพเดทฟังก์ชันจัดการคลิกเมาส์ขวาให้มีเมนูสวยงาม
function setupRightClick💾 Save() {
    const visualization = document.getElementById('visualization');
    
    visualization.addEventListener('contextmenu', function(e) {
        e.preventDefault(); // ป้องกันเมนูมาตรฐาน
        
        const view2DContainer = document.getElementById('view2DContainer');
        const threeDCanvas = visualization.querySelector('canvas');
        
        // ตรวจสอบว่ากำลังแสดงมุมมองอะไร
        const is2DView = view2DContainer && view2DContainer.style.display !== 'none';
        const is3DView = threeDCanvas && threeDCanvas.style.display !== 'none';
        
        if (is2DView || is3DView) {
            createRightClickMenu(e.clientX, e.clientY, is2DView);
        } else {
            showAlert('ไม่พบมุมมองที่สามารถบันทึกได้', 'warning');
        }
    });
}


// ✅ ฟังก์ชันบันทึกภาพมุมมองปัจจุบัน
function 💾 SaveCurrentView() {
    const view2DContainer = document.getElementById('view2DContainer');
    const threeDCanvas = document.querySelector('#visualization canvas');
    
    // ✅ ตรวจสอบให้แน่ชัด
    const is2DView = view2DContainer && view2DContainer.style.display !== 'none';
    const is3DView = threeDCanvas && threeDCanvas.style.display !== 'none' && window.currentRenderer;
    
    console.log('🔍 ตรวจสอบมุมมองปัจจุบัน:', { is2DView, is3DView });
    
    if (is2DView) {
        💾 Save2DImage();
    } else if (is3DView) {
        💾 Save3DImage();
    } else {
        showAlert('ไม่พบมุมมองที่สามารถบันทึกได้', 'warning');
    }
}

// ✅ ปรับปรุงฟังก์ชันคำนวณจำนวนชั้น
function calculateLayersUsed(plan) {
    if (!plan || plan.length === 0) return 0;
    
    // หาความสูงของสินค้าแต่ละตำแหน่ง
    const heightMap = new Map();
    
    plan.forEach(item => {
        const itemLength = item.actualSize?.length || item.length;
        const itemWidth = item.actualSize?.width || item.width;
        const itemHeight = item.actualSize?.height || item.height;
        
        // ตรวจสอบทุกตำแหน่งที่สินค้านี้ครอบครอง
        for (let x = item.position.x; x < item.position.x + itemLength; x += 10) {
            for (let y = item.position.y; y < item.position.y + itemWidth; y += 10) {
                const key = `${Math.floor(x/10)}-${Math.floor(y/10)}`;
                const currentHeight = heightMap.get(key) || 0;
                const newHeight = item.position.z + itemHeight;
                
                if (newHeight > currentHeight) {
                    heightMap.set(key, newHeight);
                }
            }
        }
    });
    
    // แปลงความสูงเป็นชั้น (สมมติว่าชั้นละ 50 cm)
    const maxHeight = Math.max(...heightMap.values());
    const layers = Math.ceil(maxHeight / 50);
    
    console.log('🏗️ จำนวนชั้นที่ใช้ (ปรับปรุงแล้ว):', {
        ความสูงสูงสุด: maxHeight,
        จำนวนชั้น: layers,
        จุดตรวจสอบ: heightMap.size
    });
    
    return Math.max(1, layers); // อย่างน้อย 1 ชั้น
}

// ✅ เพิ่มฟังก์ชันตรวจสอบตำแหน่ง Z
function validateZPositions(plan) {
    const issues = [];
    
    plan.forEach(item => {
        const itemHeight = item.actualSize?.height || item.height;
        
        // ตรวจสอบว่าสินค้าลอยอยู่หรือไม่
        if (item.position.z > 0) {
            const itemsBelow = plan.filter(other => {
                if (other.id === item.id) return false;
                
                const otherLength = other.actualSize?.length || other.length;
                const otherWidth = other.actualSize?.width || other.width;
                const otherHeight = other.actualSize?.height || other.height;
                
                // ตรวจสอบการซ้อนทับในแนว X-Y
                const overlapX = (other.position.x < item.position.x + item.length) && 
                               (other.position.x + otherLength > item.position.x);
                const overlapY = (other.position.y < item.position.y + item.width) && 
                               (other.position.y + otherWidth > item.position.y);
                
                // ตรวจสอบว่าอยู่ใต้สินค้านี้พอดี
                const isBelow = Math.abs(other.position.z + otherHeight - item.position.z) < 1;
                
                return overlapX && overlapY && isBelow;
            });
            
            if (itemsBelow.length === 0) {
                issues.push({
                    item: item.name,
                    position: item.position,
                    problem: 'ลอยอยู่ในอากาศ'
                });
            }
        }
    });
    
    if (issues.length > 0) {
        console.warn('⚠️ พบสินค้าที่วางตำแหน่งไม่ถูกต้อง:', issues);
    }
    
    return issues;
}

// เพิ่มฟังก์ชันตรวจสอบสภาพแวดล้อม
function checkEnvironment() {
    console.log('🔍 ตรวจสอบสภาพแวดล้อม:');
    console.log('- THREE:', typeof THREE);
    console.log('- getColorByItemType:', typeof getColorByItemType);
    console.log('- currentPlan:', window.currentPlan ? window.currentPlan.length : 'ไม่มี');
    console.log('- currentContainer:', window.currentContainer);
    
    // ตรวจสอบว่า Three.js โหลดแล้ว
    if (typeof THREE === 'undefined') {
        console.error('❌ Three.js ไม่ได้โหลด');
        return false;
    }
    
    return true;
}

function getColorByItemType(item) {
    // ✅ รับ parameter เป็น object หรือ string
    let itemName, itemLength, itemWidth;
    
    if (typeof item === 'string') {
        // ถ้าเป็น string (เรียกจากฟังก์ชันเก่า)
        itemName = item;
        itemLength = 0;
        itemWidth = 0;
    } else {
        // ถ้าเป็น object (เรียกจากฟังก์ชันใหม่)
        itemName = item.name || 'Unknown';
        itemLength = item.length || item.actualSize?.length || 0;
        itemWidth = item.width || item.actualSize?.width || 0;
    }
    
    // ✅ สร้าง key จากขนาดฐาน (กว้าง x ยาว)
    const roundedWidth = Math.round(itemWidth);
    const roundedLength = Math.round(itemLength);
    const baseSizeKey = `${roundedWidth}x${roundedLength}`;
    
    console.log(`🔍 กำลังกำหนดสีสำหรับ: ${itemName} (ฐาน: ${baseSizeKey} cm)`);
    
    // ✅ ถ้ามีสีสำหรับขนาดฐานนี้อยู่แล้ว ให้ใช้สีเดิม
    if (itemTypeColors.has(baseSizeKey)) {
        const color = itemTypeColors.get(baseSizeKey);
        console.log(`🎨 ใช้สีเดิมสำหรับฐาน ${baseSizeKey}: ${color}`);
        return color;
    }
    
    // ✅ ถ้ายังไม่มีสี ให้เลือกสีใหม่จากพาเลท
    const availableColors = COLOR_PALETTE.filter(color => 
        !Array.from(itemTypeColors.values()).includes(color)
    );
    
    let selectedColor;
    if (availableColors.length > 0) {
        selectedColor = availableColors[0];
    } else {
        // ✅ ถ้าสีหมดให้ใช้สีสุ่ม
        selectedColor = getRandomColor();
    }
    
    // ✅ บันทึกสีสำหรับขนาดฐานนี้
    itemTypeColors.set(baseSizeKey, selectedColor);
    
    console.log(`🎨 กำหนดสีใหม่สำหรับฐาน ${baseSizeKey} cm: ${selectedColor}`);
    return selectedColor;
}


// รีเซ็ตสีเมื่อเริ่มคำนวณใหม่
function resetItemTypeColors() {
    console.log('🔄 รีเซ็ตการ mapping สีตามขนาดฐาน');
    itemTypeColors.clear();
}


// เรียกตรวจสอบเมื่อโหลดหน้าเว็บ
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Container Loading Optimizer เริ่มทำงาน');
    checkEnvironment();
});

// ✅ เพิ่มฟังก์ชัน getColorForBaseGroup ใน app.js
function getColorForBaseGroup(baseSize) {
    const groupColors = {
        '50x50': '#FF6B6B',
        '60x60': '#4ECDC4', 
        '70x70': '#45B7D1',
        '80x80': '#96CEB4',
        '90x90': '#FFEAA7',
        '100x100': '#DDA0DD',
        '120x120': '#98D8C8',
        '150x150': '#F7DC6F',
        '200x200': '#BB8FCE'
    };
    
    const sizes = Object.keys(groupColors);
    let closestSize = sizes[0];
    let minDiff = Infinity;
    
    const [width, length] = baseSize.split('x').map(Number);
    const area = width * length;
    
    sizes.forEach(size => {
        const [sizeWidth, sizeLength] = size.split('x').map(Number);
        const sizeArea = sizeWidth * sizeLength;
        const diff = Math.abs(sizeArea - area);
        
        if (diff < minDiff) {
            minDiff = diff;
            closestSize = size;
        }
    });
    
    return groupColors[closestSize] || '#3498db';
}

// =============================================
// ทำให้ฟังก์ชันเป็น Global
// =============================================

// ฟังก์ชันสำหรับโหลดจาก CSV
window.loadItemsFromCSV = loadItemsFromCSV;
window.addSelectedCSVItems = addSelectedCSVItems;
window.initializeCSVEvents = initializeCSVEvents;

// ฟังก์ชันอื่นๆ ที่จำเป็น
window.addItem = addItem;
window.removeItem = removeItem;
window.calculateLoading = calculateLoading;
window.exportToExcel = exportToExcel;
window.setViewPreset = setViewPreset;

window.toggleViewMode = toggleViewMode;
window.createSimple2DView = createSimple2DView;

window.💾 Save2DImage = 💾 Save2DImage;
window.💾 Save3DImage = 💾 Save3DImage;
window.💾 SaveCurrentView = 💾 SaveCurrentView;

window.💾 SaveCurrentView = 💾 SaveCurrentView;
window.showItemDetailModal = showItemDetailModal;
window.showItemsGroupModal = showItemsGroupModal;

window.updateLoadingPlanTable = updateLoadingPlanTable;
window.calculateLayersUsed = calculateLayersUsed;

window.createSingleItemElement = createSingleItemElement;


// ✅ เก็บ reference ถึงแผนปัจจุบัน
window.currentPlan = [];
window.currentConditions = {};

console.log('✅ ฟังก์ชันทั้งหมดโหลดเรียบร้อยแล้ว');
