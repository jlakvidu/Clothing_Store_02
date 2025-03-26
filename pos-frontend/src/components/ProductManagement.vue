<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { connection } from '@/api/axios'
import { 
    MagnifyingGlassIcon, 
    PlusIcon,
    PencilIcon, 
    TrashIcon, 
    XMarkIcon, 
    CheckIcon,
    CubeIcon,
    CurrencyDollarIcon, 
    BuildingStorefrontIcon,
    EyeIcon,
    TagIcon,
    TruckIcon,
    BuildingOfficeIcon,
    ArrowsUpDownIcon,
    FunnelIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ArrowPathIcon,
    PhotoIcon
} from '@heroicons/vue/24/outline'
import Header from './Header.vue'
import Sidebar from './Sidebar.vue'
import Swal from 'sweetalert2'
import GRNDocument from './GRNDocument.vue'

const isSidebarVisible = ref(false)
const toggleSidebar = (visible) => {
    isSidebarVisible.value = visible
}

const showModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const showViewModal = ref(false) 
const showGRN = ref(false)
const grnProduct = ref(null)
const grnNumber = ref('')

const newProduct = ref({
    name: '',
    price: '',
    seller_price: '',
    discount: '',
    tax: '',
    size: '',
    color: '',
    description: '',
    brand_name: '',
    supplier_id: '',
    admin_id: '',
    category: '',
    quantity: 0,
    location: '',
    status: 'In Stock',
    added_stock_amount: 0
})
const editingProduct = ref({
    id: '',
    name: '',
    price: '',
    seller_price: '',
    discount: '',
    size: '',
    color: '',
    description: '',
    brand_name: '',
    supplier_id: '',
    admin_id: '',
    category: '',
    quantity: 0,
    location: '',
    status: 'In Stock',
    added_stock_amount: 0,
    profit: 0
})
const productToDelete = ref(null)
const viewingProduct = ref(null) 

const searchQuery = ref('')
const categoryFilter = ref('')
const supplierFilter = ref('')
const brandFilter = ref('')
const sortField = ref('id')
const sortDirection = ref('asc')

const newCategory = ref('')
const newSupplier = ref('')
const newBrand = ref('')
const showCategoryInput = ref(false)
const showSupplierInput = ref(false)
const showBrandInput = ref(false)

const formErrors = ref({})
const touchedFields = ref({})  

const selectedProducts = ref([])
const uploadedFile = ref(null)
const pagination = ref({
  total: 0,
  currentPage: 1,
  perPage: 10
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(value)
}

const validateInput = (field, value) => {
  if (!touchedFields.value[field]) return true
  
  if (typeof value === 'string' && /<script>/i.test(value)) {
    formErrors.value[field] = 'Invalid characters in field'
    return false
  }

  if (!value && value !== 0) {
    formErrors.value[field] = 'This field is required'
    return false
  }

  const numericFields = ['price', 'seller_price', 'tax', 'inventory_id', 'supplier_id', 'admin_id']
  if (numericFields.includes(field)) {
    const numValue = Number(value)
    if (isNaN(numValue)) {
      formErrors.value[field] = 'This field must be a number'
      return false
    }
  }

  formErrors.value[field] = ''
  return true
}

const markFieldAsTouched = (field) => {
  touchedFields.value[field] = true
  validateInput(field, newProduct.value[field])
}

const validateForm = (product) => {
  const requiredFields = ['name', 'description', 'price', 'seller_price']
  let isValid = true
  
  requiredFields.forEach(field => {
    if (!validateInput(field, product[field])) {
      isValid = false
    }
  })
  
  return isValid
}

const products = ref([])
const isLoading = ref(true)
const isAddingProduct = ref(false)
const isUpdatingProduct = ref(false)

const fetchProducts = async () => {
    isLoading.value = true
    try {
        const response = await connection.get('/products')
        products.value = response.data.data.map(product => ({
            id: product.id,
            name: product.name,
            price: product.price,
            profit: product.profit,
            seller_price: product.seller_price,
            discount: product.discount,
            size: product.size,
            color: product.color,
            description: product.description,
            category: product.category,
            quantity: product.quantity,
            location: product.location,
            status: product.status,
            brand_name: product.brand_name,
            added_stock_amount: product.added_stock_amount,
            supplier_id: product.supplier_id,
            admin_id: product.admin_id,
            created_at: product.created_at,
            updated_at: product.updated_at,
            image_url: product.image_url 
        }))
        
        if (response.data.meta) {
            pagination.value = {
                total: response.data.meta.total,
                currentPage: response.data.meta.current_page,
                perPage: response.data.meta.per_page
            }
        }
    } catch (error) {
        console.error('Error fetching products:', error)
        Swal.fire({
            icon: "error",
            title: "Error!",
            text: error.response?.data?.message || 'Failed to load products',
            background: '#1e293b',
            color: '#ffffff'
        })
    } finally {
        isLoading.value = false
    }
}

const filteredProducts = computed(() => {
    let result = products.value.filter(product => {
        const matchesSearch = searchQuery.value === '' || 
            product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            product.id.toString().toLowerCase().includes(searchQuery.value.toLowerCase())
        const matchesCategory = !categoryFilter.value || product.category === categoryFilter.value
        const matchesSupplier = !supplierFilter.value || product.supplier === supplierFilter.value
        const matchesBrand = !brandFilter.value || product.brand === brandFilter.value
        return matchesSearch && matchesCategory && matchesSupplier && matchesBrand
    })
    
    result.sort((a, b) => {
        let fieldA = a[sortField.value]
        let fieldB = b[sortField.value]
        
        if (sortField.value === 'quantity') {
            fieldA = Number(fieldA)
            fieldB = Number(fieldB)
        }
        
        if (sortField.value === 'discount') {
            fieldA = Number(fieldA.replace('%', ''))
            fieldB = Number(fieldB.replace('%', ''))
        }
        
        if (fieldA < fieldB) return sortDirection.value === 'asc' ? -1 : 1
        if (fieldA > fieldB) return sortDirection.value === 'asc' ? 1 : -1
        return 0
    })
    
    return result
})

const activeFiltersCount = computed(() => {
    let count = 0
    if (categoryFilter.value) count++
    if (supplierFilter.value) count++
    if (brandFilter.value) count++
    return count
})

const resetFilters = () => {
    categoryFilter.value = ''
    supplierFilter.value = ''
    brandFilter.value = ''
    searchQuery.value = ''
}

const handleAddSubmit = () => {
  const requiredFields = ['name', 'description', 'price', 'seller_price']
  requiredFields.forEach(field => markFieldAsTouched(field))

  if (!validateForm(newProduct.value)) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please fill in all required fields',
      background: '#1e293b',
      color: '#ffffff'
    })
    return
  }
  handleAddProduct()
}

const handleAddProduct = async () => {
  if (!validateForm(newProduct.value)) return
  isAddingProduct.value = true

  try {
    const payload = {
      name: newProduct.value.name,
      price: parseFloat(newProduct.value.price),
      seller_price: parseFloat(newProduct.value.seller_price),
      discount: newProduct.value.discount ? parseFloat(newProduct.value.discount) : 0,
      tax: parseFloat(newProduct.value.tax),
      size: newProduct.value.size,
      color: newProduct.value.color,
      description: newProduct.value.description,
      brand_name: newProduct.value.brand_name,
      supplier_id: parseInt(newProduct.value.supplier_id),
      admin_id: parseInt(newProduct.value.admin_id),
      category: newProduct.value.category,
      quantity: parseInt(newProduct.value.quantity),
      location: newProduct.value.location,
      status: newProduct.value.status,
      added_stock_amount: parseInt(newProduct.value.added_stock_amount)
    }

    const response = await connection.post('/products', payload)
    
    if (response.data.status === 'success') {
      const newProductData = {
        id: response.data.data.product.id,
        name: response.data.data.product.name,
        price: response.data.data.product.price,
        profit: response.data.data.product.profit,
        seller_price: response.data.data.product.seller_price,
        discount: response.data.data.product.discount,
        size: response.data.data.product.size,
        color: response.data.data.product.color,
        description: response.data.data.product.description,
        category: response.data.data.product.category,
        quantity: response.data.data.product.quantity,
        location: response.data.data.product.location,
        status: response.data.data.product.status,
        brand_name: response.data.data.product.brand_name,
        added_stock_amount: response.data.data.product.added_stock_amount,
        supplier_id: response.data.data.product.supplier_id,
        admin_id: response.data.data.product.admin_id,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      products.value.unshift(newProductData)
      
      grnProduct.value = {
        ...response.data.data.product,
        supplierDetails: response.data.data.product.supplierDetails || {}
      }
      grnNumber.value = response.data.data.grn.number
      
      showModal.value = false
      showGRN.value = true

      newProduct.value = {
        name: '',
        price: '',
        seller_price: '',
        discount: '',
        tax: '',
        size: '',
        color: '',
        description: '',
        brand_name: '',
        supplier_id: '',
        admin_id: '',
        category: '',
        quantity: 0,
        location: '',
        status: 'In Stock',
        added_stock_amount: 0
      }

      Swal.fire({
        position: "center",
        icon: "success",
        title: "Product Added Successfully!",
        showConfirmButton: false,
        timer: 1500,
        background: '#1e293b',
        color: '#ffffff'
      })
    }
  } catch (error) {
    if (error.response?.status === 429) {
        Swal.fire({
            icon: 'warning',
            title: 'Rate Limited',
            text: 'Please try again later',
            background: '#1e293b',
            color: '#ffffff'
        })
        return
    }

    let errorMessage = 'Failed to add product'
    
    if (error.response?.data?.message) {
        if (typeof error.response.data.message === 'object') {
            errorMessage = Object.values(error.response.data.message).flat().join('\n')
        } else {
            errorMessage = error.response.data.message
        }
    } else if (error.message) {
        errorMessage = error.message
    }

    console.error('Error adding product:', error)
    
    Swal.fire({
        icon: "error",
        title: "Error!",
        text: errorMessage,
        background: '#1e293b',
        color: '#ffffff'
    })
  } finally {
    isAddingProduct.value = false
  }
}

const openEditModal = (product) => {
    editingProduct.value = {
        id: product.id,
        name: product.name,
        price: product.price,
        profit: product.profit,
        seller_price: product.seller_price,
        discount: product.discount,
        size: product.size,
        color: product.color,
        description: product.description,
        brand_name: product.brand_name,
        supplier_id: product.supplier_id,
        admin_id: product.admin_id,
        category: product.category,
        quantity: product.quantity,
        location: product.location,
        status: product.status,
        added_stock_amount: product.added_stock_amount
    }
    showEditModal.value = true
}

const handleEditProduct = async () => {
    if (!validateForm(editingProduct.value)) return
    isUpdatingProduct.value = true

    try {
        const payload = {
            name: editingProduct.value.name,
            price: parseFloat(editingProduct.value.price),
            seller_price: parseFloat(editingProduct.value.seller_price),
            discount: parseFloat(editingProduct.value.discount || 0),
            size: editingProduct.value.size,
            color: editingProduct.value.color,
            description: editingProduct.value.description,
            category: editingProduct.value.category,
            quantity: parseInt(editingProduct.value.quantity),
            location: editingProduct.value.location,
            status: editingProduct.value.status,
            brand_name: editingProduct.value.brand_name,
            supplier_id: parseInt(editingProduct.value.supplier_id),
            admin_id: parseInt(editingProduct.value.admin_id),
            added_stock_amount: parseInt(editingProduct.value.added_stock_amount || 0)
        }

        const response = await connection.put(`/products/${editingProduct.value.id}`, payload)

        if (response.data.status === 'success') {
            const index = products.value.findIndex(p => p.id === editingProduct.value.id)
            if (index !== -1) {
                products.value[index] = response.data.data
            }

            showEditModal.value = false
            Swal.fire({
                position: "center",
                icon: "success",
                title: "Product Updated Successfully!",
                showConfirmButton: false,
                timer: 1500,
                background: '#1e293b',
                color: '#ffffff'
            })
        }
    } catch (error) {
        console.error('Error updating product:', error)
        let errorMessage = 'Failed to update product'
        
        if (error.response?.data?.message) {
            errorMessage = typeof error.response.data.message === 'object' 
                ? Object.values(error.response.data.message).flat().join('\n')
                : error.response.data.message
        }

        Swal.fire({
            icon: "error",
            title: "Error!",
            text: errorMessage,
            background: '#1e293b',
            color: '#ffffff'
        })
    } finally {
        isUpdatingProduct.value = false
    }
}

const openDeleteModal = (product) => {
    Swal.fire({
        title: "Are you sure?",
        text: `Do you want to delete product "${product.name}"?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        background: '#1e293b',
        color: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            handleDeleteProduct(product)
        }
    })
}

const handleDeleteProduct = async (product) => {
    try {
        await connection.delete(`/products/${product.id}`)
        products.value = products.value.filter(p => p.id !== product.id)
        
        Swal.fire({
            position: "center",
            icon: "success",
            title: "Product Deleted Successfully!",
            showConfirmButton: false,
            timer: 1500,
            background: '#1e293b',
            color: '#ffffff'
        })
    } catch (error) {
        console.error('Error deleting product:', error)
        Swal.fire({
            icon: "error",
            title: "Error!",
            text: "Failed to delete product",
            background: '#1e293b',
            color: '#ffffff'
        })
    }
}

const handleEditSubmit = async () => {
  if (!validateForm(editingProduct.value)) {
    Swal.fire({
      icon: "error",
      title: "Validation Error",
      text: "Please fill in all required fields",
      background: '#1e293b',
      color: '#ffffff'
    })
    return
  }
  await handleEditProduct()
}

const addNewCategory = () => {
    if (newCategory.value && !categories.value.includes(newCategory.value)) {
        categories.value.push(newCategory.value)
        newProduct.value.category = newCategory.value
        newCategory.value = ''
        
        Swal.fire({
            position: "center",
            icon: "success",
            title: "New Category Added!",
            showConfirmButton: false,
            timer: 1500,
            background: '#1e293b',
            color: '#ffffff'
        })
    }
    showCategoryInput.value = false
}

const addNewSupplier = () => {
    if (newSupplier.value && !suppliers.value.includes(newSupplier.value)) {
        suppliers.value.push(newSupplier.value)
        newProduct.value.supplier = newSupplier.value
        newSupplier.value = ''
        
        Swal.fire({
            position: "center",
            icon: "success",
            title: "New Supplier Added!",
            showConfirmButton: false,
            timer: 1500,
            background: '#1e293b',
            color: '#ffffff'
        })
    }
    showSupplierInput.value = false
}

const addNewBrand = () => {
    if (newBrand.value && !brands.value.includes(newBrand.value)) {
        brands.value.push(newBrand.value)
        newProduct.value.brand = newBrand.value
        newBrand.value = ''
        
        Swal.fire({
            position: "center",
            icon: "success",
            title: "New Brand Added!",
            showConfirmButton: false,
            timer: 1500,
            background: '#1e293b',
            color: '#ffffff'
        })
    }
    showBrandInput.value = false
}

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'asc'
    }
}

const getSortIcon = (field) => {
    if (sortField.value !== field) {
        return ArrowsUpDownIcon
    }
    return sortDirection.value === 'asc' ? ChevronUpIcon : ChevronDownIcon
}

const refreshData = async () => {
    isLoading.value = true
    await fetchProducts()
    Swal.fire({
        position: "center",
        icon: "success",
        title: "Data Refreshed!",
        showConfirmButton: false,
        timer: 1000,
        background: '#1e293b',
        color: '#ffffff'
    })
}

const openViewModal = (product) => {
    viewingProduct.value = product
    showViewModal.value = true
}

const restoreFormData = () => {
  const savedData = localStorage.getItem('draft_product')
  if (savedData) {
    try {
      newProduct.value = JSON.parse(savedData)
    } catch (e) {
      console.error('Error restoring form data:', e)
    }
  }
}

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (file && file.type.startsWith('image/')) {
    uploadedFile.value = file
    return true
  }
  return false
}

const handleBatchDelete = async () => {
  if (!selectedProducts.value?.length) return
    
  try {
      await Promise.all(
          selectedProducts.value.map(id => connection.delete(`/products/${id}`))
      )
      selectedProducts.value = []
  } catch (error) {
      console.error('Error in batch delete:', error)
  }
}

const showImageUploadModal = ref(false)
const selectedProductId = ref(null)
const selectedFile = ref(null)
const imagePreview = ref(null)
const isUploadingImage = ref(false)

const handleFileChange = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        const reader = new FileReader()
        reader.onload = (e) => {
            imagePreview.value = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const openImageUploadModal = (productId) => {
    selectedProductId.value = productId
    selectedFile.value = null
    imagePreview.value = null
    showImageUploadModal.value = true
}

const uploadProductImage = async () => {
    if (!selectedFile.value) return
    isUploadingImage.value = true
    
    try {
        const formData = new FormData()
        formData.append('image', selectedFile.value)
        
        const response = await connection.post(`/products/${selectedProductId.value}/image`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })

        if (response.data.status === 'success') {
            showImageUploadModal.value = false
            Swal.fire({
                position: "center",
                icon: "success",
                title: "Image uploaded successfully!",
                showConfirmButton: false,
                timer: 1500,
                background: '#1e293b',
                color: '#ffffff'
            })
            console.log('Image URL:', response.data.data.url)
            await fetchProducts()
        }
    } catch (error) {
        console.error('Error uploading image:', error)
        Swal.fire({
            icon: "error",
            title: "Error!",
            text: error.response?.data?.message || "Failed to upload image",
            background: '#1e293b',
            color: '#ffffff'
        })
    } finally {
        isUploadingImage.value = false
    }
}

onMounted(() => {
    fetchProducts()
    restoreFormData() 
})

onUnmounted(() => {
  products.value = []
  selectedProducts.value = []
  uploadedFile.value = null
  localStorage.removeItem('draft_product')
})
</script>

<template>
    <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white min-h-screen">
        <Header />
        <Sidebar :isVisible="isSidebarVisible" @closeSidebar="toggleSidebar(false)" />
        <div class="fixed top-0 left-0 w-8 h-full z-50" @mouseenter="toggleSidebar(true)"></div>
        <div class="ml-0 pt-20"> 
            <div class="w-full h-full flex flex-col p-4 md:p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 bg-clip-text text-transparent">
                            Products Management
                        </h1>
                    </div>
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-4 w-full md:w-auto">
                        <div class="relative w-full md:w-80">
                            <input 
                                v-model="searchQuery" 
                                type="search" 
                                placeholder="Search by ID or name..."
                                class="w-full px-4 py-2 bg-gray-800 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 border border-gray-700 pl-10"
                            >
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                        </div>
                        <button 
                            @click="showModal = true"
                            class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 font-medium inline-flex items-center transition-colors w-full md:w-auto justify-center"
                        >
                            <PlusIcon class="w-5 h-5 mr-2" />
                            Add Product
                        </button>
                    </div>
                </div>

                <div class="flex-1 bg-gray-800/50 backdrop-blur-sm rounded-lg overflow-hidden shadow-xl border border-gray-700/50">
                    <div class="h-full overflow-auto">
                        <table class="w-full table-auto">
                            <thead class="sticky top-0">
                                <tr class="bg-gray-700/90 backdrop-blur-sm">
                                    <th
                                        @click="toggleSort('id')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>ID</span>
                                            <component :is="getSortIcon('id')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Image
                                    </th>
                                    <th
                                        @click="toggleSort('name')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Name</span>
                                            <component :is="getSortIcon('name')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        @click="toggleSort('category')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Category</span>
                                            <component :is="getSortIcon('category')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        @click="toggleSort('quantity')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Quantity</span>
                                            <component :is="getSortIcon('quantity')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        @click="toggleSort('price')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Price</span>
                                            <component :is="getSortIcon('price')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        @click="toggleSort('seller_price')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Seller Price</span>
                                            <component :is="getSortIcon('seller_price')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        @click="toggleSort('status')"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-600/50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-1">
                                            <span>Status</span>
                                            <component :is="getSortIcon('status')" class="w-4 h-4" />
                                        </div>
                                    </th>
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                <tr v-if="isLoading" class="hover:bg-gray-700">
                                    <td colspan="9" class="h-[400px] relative">
                                        <div class="absolute inset-0 flex flex-col items-center justify-center space-y-4">
                                            <div class="loader-container">
                                                <div class="loader">
                                                    <svg class="circular" viewBox="25 25 50 50">
                                                        <defs>
                                                            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%">
                                                                <stop offset="0%" stop-color="#3b82f6" />
                                                                <stop offset="50%" stop-color="#8b5cf6" />
                                                                <stop offset="100%" stop-color="#ec4899" />
                                                            </linearGradient>
                                                        </defs>
                                                        <circle class="path" cx="50" cy="50" r="20" fill="none" stroke="url(#gradient)" stroke-width="3" stroke-miterlimit="10"/>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="text-base font-medium text-transparent bg-clip-text bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 animate-pulse">
                                                Loading products...
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr v-if="filteredProducts.length === 0" class="hover:bg-gray-700">
                                        <td colspan="9" class="px-6 py-8 text-center text-gray-400">
                                            No products available
                                        </td>
                                    </tr>
                                    <tr v-else v-for="product in filteredProducts" 
                                        :key="product.id"
                                        class="hover:bg-gray-700/30 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="font-mono bg-gray-700/50 px-2 py-1 rounded text-gray-300">{{ product.id }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200">
                                            <div v-if="product.image_url" class="w-10 h-10 rounded-full overflow-hidden border-2 border-gray-700 hover:border-blue-500 transition-colors duration-300">
                                                <img 
                                                    :src="product.image_url" 
                                                    :alt="product.name"
                                                    class="w-full h-full object-cover"
                                                    @error="(e) => {
                                                        e.target.src = '';
                                                        console.error('Failed to load image:', product.image_url);
                                                    }"
                                                >
                                            </div>
                                            <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
                                                {{ product.name.charAt(0).toUpperCase() }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ product.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                                {{ product.category }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ product.quantity }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">${{ Number(product.price).toFixed(2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">${{ Number(product.seller_price).toFixed(2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span :class="{
                                                'px-2 py-1 text-xs rounded-full': true,
                                                'bg-green-500/20 text-green-300 border border-green-500/30': product.status === 'In Stock',
                                                'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30': product.status === 'Low Stock',
                                                'bg-red-500/20 text-red-300 border border-red-500/30': product.status === 'Out of Stock'
                                            }">
                                                {{ product.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button 
                                                    @click="openViewModal(product)"
                                                    class="text-cyan-400 hover:text-cyan-300 p-1.5 hover:bg-gray-700 rounded-full transition-colors"
                                                    title="View Details"
                                                >
                                                    <EyeIcon class="w-5 h-5" />
                                                </button>
                                                <button 
                                                    @click="openEditModal(product)"
                                                    class="text-purple-400 hover:text-purple-300 p-1.5 hover:bg-gray-700 rounded-full transition-colors"
                                                    title="Edit Product"
                                                >
                                                    <PencilIcon class="w-5 h-5" />
                                                </button>
                                                <button 
                                                    @click="openDeleteModal(product)"
                                                    class="text-rose-500 hover:text-rose-400 p-1.5 hover:bg-gray-700 rounded-full transition-colors"
                                                    title="Delete Product"
                                                >
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                                <button 
                                                    @click="openImageUploadModal(product.id)"
                                                    class="text-blue-400 hover:text-blue-300 p-1.5 hover:bg-gray-700 rounded-full transition-colors"
                                                    title="Upload Image"
                                                >
                                                    <PhotoIcon class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4 text-sm text-gray-400 flex justify-between items-center">
                    <div>
                        Showing {{ filteredProducts.length }} of {{ products.length }} products
                    </div>
                    <div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
            <div 
                class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-lg w-full max-w-3xl p-6 shadow-xl border border-gray-700/50 max-h-[90vh] overflow-auto"
                @click.stop
            >
                <div class="flex justify-between items-center mb-6 border-b border-gray-700 pb-4">
                    <div class="flex items-center space-x-2">
                        <CubeIcon class="w-6 h-6 text-blue-500" />
                        <h2 class="text-xl font-semibold">Add New Product</h2>
                    </div>
                    <button 
                        @click="showModal = false"
                        class="text-gray-400 hover:text-gray-200 hover:bg-gray-700 p-2 rounded-full transition-colors"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="handleAddSubmit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Basic Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Product Name</label>
                                <input 
                                    v-model="newProduct.name" 
                                    @blur="markFieldAsTouched('name')"
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.name && formErrors.name
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.name && formErrors.name" class="text-red-500 text-xs mt-1">{{ formErrors.name }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                                <textarea 
                                    v-model="newProduct.description"
                                    @blur="markFieldAsTouched('description')"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.description && formErrors.description
                                    }"
                                    required
                                    rows="3"
                                ></textarea>
                                <span v-if="touchedFields.description && formErrors.description" class="text-red-500 text-xs mt-1">{{ formErrors.description }}</span>
                            </div>
                        </div>

                        <div class="bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Pricing</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Price</label>
                                <input 
                                    v-model="newProduct.price" 
                                    @blur="markFieldAsTouched('price')"
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.price && formErrors.price
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.price && formErrors.price" class="text-red-500 text-xs mt-1">{{ formErrors.price }}</span>
                            </div>
                        </div>

                        <div class="bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Product Details</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Size</label>
                                <input 
                                    v-model="newProduct.size" 
                                    @blur="markFieldAsTouched('size')"
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.size && formErrors.size
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.size && formErrors.size" class="text-red-500 text-xs mt-1">{{ formErrors.size }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Color</label>
                                <input 
                                    v-model="newProduct.color" 
                                    @blur="markFieldAsTouched('color')"
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.color && formErrors.color
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.color && formErrors.color" class="text-red-500 text-xs mt-1">{{ formErrors.color }}</span>
                            </div>
                        </div>

                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Stock Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category</label>
                                <input 
                                    v-model="newProduct.category" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Quantity</label>
                                <input 
                                    v-model="newProduct.quantity" 
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Location</label>
                                <input 
                                    v-model="newProduct.location" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                                <select 
                                    v-model="newProduct.status"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                                    <option value="In Stock">In Stock</option>
                                    <option value="Low Stock">Low Stock</option>
                                    <option value="Out of Stock">Out of Stock</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Added Stock Amount</label>
                                <input 
                                    v-model="newProduct.added_stock_amount" 
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>
                        </div>

                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Additional Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Brand Name</label>
                                <input 
                                    v-model="newProduct.brand_name" 
                                    @blur="markFieldAsTouched('brand_name')"
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.brand_name && formErrors.brand_name
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.brand_name && formErrors.brand_name" class="text-red-500 text-xs mt-1">{{ formErrors.brand_name }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Seller Price</label>
                                <input 
                                    v-model="newProduct.seller_price" 
                                    @blur="markFieldAsTouched('seller_price')"
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.seller_price && formErrors.seller_price
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.seller_price && formErrors.seller_price" class="text-red-500 text-xs mt-1">{{ formErrors.seller_price }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Discount (%)</label>
                                <input 
                                    v-model="newProduct.discount" 
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    placeholder="0"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Supplier ID</label>
                                <input 
                                    v-model="newProduct.supplier_id" 
                                    @blur="markFieldAsTouched('supplier_id')"
                                    type="number"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.supplier_id && formErrors.supplier_id
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.supplier_id && formErrors.supplier_id" class="text-red-500 text-xs mt-1">{{ formErrors.supplier_id }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Admin ID</label>
                                <input 
                                    v-model="newProduct.admin_id" 
                                    @blur="markFieldAsTouched('admin_id')"
                                    type="number"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    :class="{
                                        'border-red-500': touchedFields.admin_id && formErrors.admin_id
                                    }"
                                    required
                                >
                                <span v-if="touchedFields.admin_id && formErrors.admin_id" class="text-red-500 text-xs mt-1">{{ formErrors.admin_id }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-700">
                        <button 
                            type="button" 
                            @click="showModal = false"
                            class="px-4 py-2.5 text-gray-300 hover:text-white bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors duration-200 flex items-center"
                        >
                            <XMarkIcon class="w-5 h-5 mr-2" />
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="!validateForm(newProduct) || isAddingProduct"
                            :class="[
                                'px-4 py-2.5 rounded-lg transition-all duration-300 flex items-center space-x-2',
                                validateForm(newProduct) && !isAddingProduct
                                    ? 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-800 hover:shadow-lg hover:shadow-blue-500/30 text-white' 
                                    : 'bg-gray-600 cursor-not-allowed text-gray-400'
                            ]"
                        >
                            <template v-if="isAddingProduct">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Adding Product...</span>
                            </template>
                            <template v-else>
                                <CheckIcon class="w-5 h-5 mr-2" />
                                Add Product
                            </template>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
            <div 
                class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-lg w-full max-w-3xl p-6 shadow-xl border border-gray-700/50 max-h-[90vh] overflow-auto"
                @click.stop
            >
                <div class="flex justify-between items-center mb-6 border-b border-gray-700 pb-4">
                    <div class="flex items-center space-x-2">
                        <PencilIcon class="w-6 h-6 text-purple-400" />
                        <h2 class="text-xl font-semibold">Edit Product</h2>
                    </div>
                    <button 
                        @click="showEditModal = false"
                        class="text-gray-400 hover:text-gray-200 hover:bg-gray-700 p-2 rounded-full transition-colors"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="handleEditSubmit" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Basic Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Product Name</label>
                                <input 
                                    v-model="editingProduct.name" 
                                    @blur="markFieldAsTouched('name')"
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                                <textarea 
                                    v-model="editingProduct.description"
                                    @blur="markFieldAsTouched('description')"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                    rows="3"
                                ></textarea>
                            </div>
                        </div>

                        <div class="bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Pricing</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Price</label>
                                <input 
                                    v-model="editingProduct.price" 
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>
                        </div>

                        <div class="bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Product Details</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Size</label>
                                <input 
                                    v-model="editingProduct.size" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Color</label>
                                <input 
                                    v-model="editingProduct.color" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>
                        </div>

                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Stock Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Category</label>
                                <input 
                                    v-model="editingProduct.category" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Quantity</label>
                                <input 
                                    v-model="editingProduct.quantity" 
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Location</label>
                                <input 
                                    v-model="editingProduct.location" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                                <select 
                                    v-model="editingProduct.status"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                                    <option value="In Stock">In Stock</option>
                                    <option value="Low Stock">Low Stock</option>
                                    <option value="Out of Stock">Out of Stock</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Added Stock Amount</label>
                                <input 
                                    v-model="editingProduct.added_stock_amount" 
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>
                        </div>

                        <div class="md:col-span-2 bg-gray-750 p-4 rounded-lg space-y-4">
                            <h3 class="text-sm font-medium text-gray-300 uppercase tracking-wider mb-3">Additional Information</h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Brand Name</label>
                                <input 
                                    v-model="editingProduct.brand_name" 
                                    type="text"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Seller Price</label>
                                <input 
                                    v-model="editingProduct.seller_price" 
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Discount (%)</label>
                                <input 
                                    v-model="editingProduct.discount" 
                                    type="number"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Supplier ID</label>
                                <input 
                                    v-model="editingProduct.supplier_id" 
                                    type="number"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Admin ID</label>
                                <input 
                                    v-model="editingProduct.admin_id" 
                                    type="number"
                                    class="w-full px-4 py-2.5 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 border border-gray-600"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-700">
                        <button 
                            type="button" 
                            @click="showEditModal = false"
                            class="px-4 py-2.5 text-gray-300 hover:text-white bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors duration-200 flex items-center"
                        >
                            <XMarkIcon class="w-5 h-5 mr-2" />
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="!validateForm(editingProduct) || isUpdatingProduct"
                            :class="[
                                'px-4 py-2.5 rounded-lg transition-all duration-300 flex items-center space-x-2',
                                validateForm(editingProduct) && !isUpdatingProduct
                                    ? 'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 hover:shadow-lg hover:shadow-purple-500/30 text-white' 
                                    : 'bg-gray-600 cursor-not-allowed text-gray-400'
                            ]"
                        >
                            <template v-if="isUpdatingProduct">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Updating Product...</span>
                            </template>
                            <template v-else>
                                <CheckIcon class="w-5 h-5 mr-2" />
                                Save Changes 
                            </template>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showViewModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
            <div 
                class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-lg w-full max-w-xl p-6 shadow-xl border border-gray-700/50 max-h-[90vh] overflow-auto"
                @click.stop
            >
                <div class="flex justify-between items-center mb-6 border-b border-gray-700/50 pb-4">
                    <div class="flex items-center space-x-2">
                        <EyeIcon class="w-6 h-6 text-cyan-400" />
                        <h2 class="text-xl font-semibold text-cyan-400">Product Details</h2>
                    </div>
                    <button 
                        @click="showViewModal = false"
                        class="text-gray-400 hover:text-gray-200 hover:bg-gray-700 p-2 rounded-full transition-colors"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-6" v-if="viewingProduct">
                    <div class="flex justify-center mb-6">
                        <div v-if="viewingProduct.image_url" class="w-32 h-32 rounded-full overflow-hidden border-4 border-cyan-500/30">
                            <img 
                                :src="viewingProduct.image_url" 
                                :alt="viewingProduct.name"
                                class="w-full h-full object-cover"
                                @error="(e) => e.target.src = ''"
                            >
                        </div>
                        <div v-else class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-4xl font-bold border-4 border-cyan-500/30">
                            {{ viewingProduct.name?.charAt(0).toUpperCase() }}
                        </div>
                    </div>

                    <div class="bg-gray-800/50 backdrop-blur-sm p-4 rounded-lg border border-gray-700/30 space-y-4">
                        <h3 class="text-sm font-medium text-gray-300 uppercase">Basic Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-cyan-400">ID</span>
                                <p class="text-white font-mono bg-gray-700/50 px-2 py-1 rounded mt-1">{{ viewingProduct.id }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Name</span>
                                <p class="text-white mt-1">{{ viewingProduct.name }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-sm font-medium text-cyan-400">Description</span>
                                <p class="text-white mt-1">{{ viewingProduct.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 backdrop-blur-sm p-4 rounded-lg border border-gray-700/30 space-y-4">
                        <h3 class="text-sm font-medium text-gray-300 uppercase">Pricing Details</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Price</span>
                                <p class="text-white mt-1">${{ Number(viewingProduct.price).toFixed(2) }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Seller Price</span>
                                <p class="text-white mt-1">${{ Number(viewingProduct.seller_price).toFixed(2) }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Profit</span>
                                <p class="text-white mt-1">${{ Number(viewingProduct.profit).toFixed(2) }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Discount</span>
                                <p class="text-white mt-1">{{ Number(viewingProduct.discount).toFixed(2) }}%</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 backdrop-blur-sm p-4 rounded-lg border border-gray-700/30 space-y-4">
                        <h3 class="text-sm font-medium text-gray-300 uppercase">Product Details</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Category</span>
                                <p class="text-white mt-1">{{ viewingProduct.category }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Brand Name</span>
                                <p class="text-white mt-1">{{ viewingProduct.brand_name }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Size</span>
                                <p class="text-white mt-1">{{ viewingProduct.size || 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Color</span>
                                <p class="text-white mt-1">{{ viewingProduct.color || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 backdrop-blur-sm p-4 rounded-lg border border-gray-700/30 space-y-4">
                        <h3 class="text-sm font-medium text-gray-300 uppercase">Stock Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Quantity</span>
                                <p class="text-white mt-1">{{ viewingProduct.quantity }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Added Stock Amount</span>
                                <p class="text-white mt-1">{{ viewingProduct.added_stock_amount }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Status</span>
                                <p class="text-white mt-1">
                                    <span :class="{
                                        'px-2 py-1 text-xs rounded-full': true,
                                        'bg-green-500/20 text-green-300 border border-green-500/30': viewingProduct.status === 'In Stock',
                                        'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30': viewingProduct.status === 'Low Stock',
                                        'bg-red-500/20 text-red-300 border border-red-500/30': viewingProduct.status === 'Out of Stock'
                                    }">
                                        {{ viewingProduct.status }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Location</span>
                                <p class="text-white mt-1">{{ viewingProduct.location || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 backdrop-blur-sm p-4 rounded-lg border border-gray-700/30 space-y-4">
                        <h3 class="text-sm font-medium text-gray-300 uppercase">System Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Supplier ID</span>
                                <p class="text-white font-mono bg-gray-700/50 px-2 py-1 rounded mt-1">{{ viewingProduct.supplier_id }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Admin ID</span>
                                <p class="text-white font-mono bg-gray-700/50 px-2 py-1 rounded mt-1">{{ viewingProduct.admin_id }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Created At</span>
                                <p class="text-white mt-1">{{ new Date(viewingProduct.created_at).toLocaleString() }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-cyan-400">Updated At</span>
                                <p class="text-white mt-1">{{ new Date(viewingProduct.updated_at).toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6 pt-4 border-t border-gray-700">
                    <button 
                        @click="showViewModal = false"
                        class="px-4 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center"
                    >
                        <XMarkIcon class="w-5 h-5 mr-2" />
                        Close
                    </button>
                </div>
            </div>
        </div>
        <GRNDocument 
            v-if="showGRN"
            :productData="grnProduct"
            :grnNumber="grnNumber"
            :showModal="showGRN"
            @close="showGRN = false"
        />

        <div v-if="showImageUploadModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
            <div class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-lg w-full max-w-md p-6 shadow-xl border border-gray-700/50">
                <div class="flex justify-between items-center mb-6 border-b border-gray-700 pb-4">
                    <div class="flex items-center space-x-2">
                        <PhotoIcon class="w-6 h-6 text-blue-400" />
                        <h2 class="text-xl font-semibold text-blue-400">Upload Product Image</h2>
                    </div>
                    <button 
                        @click="showImageUploadModal = false"
                        class="text-gray-400 hover:text-gray-200 hover:bg-gray-700 p-2 rounded-full transition-colors"
                    >
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="border-2 border-dashed border-gray-600 rounded-lg p-4 text-center">
                        <input 
                            type="file" 
                            accept="image/*" 
                            class="hidden" 
                            id="imageInput" 
                            @change="handleFileChange"
                        >
                        <label 
                            for="imageInput"
                            class="cursor-pointer flex flex-col items-center justify-center space-y-2"
                        >
                            <PhotoIcon class="w-12 h-12 text-gray-400" />
                            <span class="text-gray-400">Click to select image</span>
                        </label>
                    </div>

                    <div v-if="imagePreview" class="mt-4">
                        <img 
                            :src="imagePreview" 
                            alt="Preview" 
                            class="max-h-48 rounded-lg mx-auto"
                        />
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-700">
                    <button 
                        @click="showImageUploadModal = false"
                        class="px-4 py-2.5 text-gray-300 hover:text-white bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="uploadProductImage"
                        :disabled="!selectedFile || isUploadingImage"
                        :class="[
                            'px-4 py-2.5 rounded-lg transition-colors flex items-center space-x-2',
                            selectedFile && !isUploadingImage
                                ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                : 'bg-gray-600 cursor-not-allowed text-gray-400'
                        ]"
                    >
                        <template v-if="isUploadingImage">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Uploading...</span>
                        </template>
                        <template v-else>
                            <PhotoIcon class="w-5 h-5 mr-2" />
                            <span>Upload Image</span>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.overflow-auto {
    height: calc(100vh - 200px);
}

table {
    border-collapse: collapse;
    width: 100%;
}

thead {
    position: sticky;
    top: 0;
    z-index: 1;
}

tbody tr:last-child td {
    border-bottom: none;
}

.bg-gray-750 {
    background-color: rgba(55, 65, 81, 0.5);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

.animate-slideIn {
    animation: slideIn 0.3s ease-out;
}

::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(31, 41, 55, 0.5);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.5);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(107, 114, 128, 0.5);
}

.loader-container {
    position: relative;
    width: 100px;
    height: 100px;
}

.loader {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70px;
    height: 70px;
}

.circular {
    animation: rotate 2s linear infinite;
    height: 100%;
    width: 100%;
    transform-origin: center center;
}

.path {
    stroke-dasharray: 89, 200;
    stroke-dashoffset: 0;
    stroke-linecap: round;
    animation: dash 1.5s ease-in-out infinite;
}

@keyframes rotate {
    100% {
        transform: rotate(360deg);
    }
}

@keyframes dash {
    0% {
        stroke-dasharray: 1, 200;
        stroke-dashoffset: 0;
    }
    50% {
        stroke-dasharray: 89, 200;
        stroke-dashoffset: -35;
    }
    100% {
        stroke-dasharray: 89, 200;
        stroke-dashoffset: -124;
    }
}
</style>